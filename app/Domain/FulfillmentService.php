<?php

namespace App\Domain;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\ProviderName;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\Product;
use App\Providers\DigitalGoods\ProviderRegistry;
use App\Providers\DigitalGoods\ProviderResult;
use App\Providers\DigitalGoods\ProviderTimeoutException;
use App\Support\CommerceLog;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class FulfillmentService
{
    public function __construct(
        private readonly ProviderRegistry $providers,
        private readonly OrderStateMachine $stateMachine,
        private readonly LedgerService $ledger,
    ) {}

    public function fulfill(string $orderId): void
    {
        $claimed = $this->claim($orderId);

        if ($claimed === null) {
            return;
        }

        [$order, $fulfillment, $ownsCall] = $claimed;

        if (! $ownsCall) {
            return;
        }

        if ($fulfillment->status === FulfillmentStatus::Succeeded) {
            return;
        }

        if ($fulfillment->status === FulfillmentStatus::Unknown && $fulfillment->provider !== null) {
            $this->resolveUnknown($order, $fulfillment, $fulfillment->provider);

            return;
        }

        $this->tryProvider($order, $fulfillment, ProviderName::A);
    }

    /**
     * @return array{0: Order, 1: Fulfillment, 2: bool}|null
     */
    private function claim(string $orderId): ?array
    {
        return DB::transaction(function () use ($orderId) {
            /** @var Order|null $order */
            $order = Order::query()->where('id', $orderId)->lockForUpdate()->first();

            if ($order === null) {
                return null;
            }

            if ($order->status === OrderStatus::Delivered) {
                return null;
            }

            if ($order->status === OrderStatus::PendingPayment) {
                return null;
            }

            if ($order->status === OrderStatus::Paid) {
                $this->stateMachine->transition($order, OrderStatus::Fulfilling);
            }

            if ($order->status === OrderStatus::Failed) {
                $this->stateMachine->transition($order, OrderStatus::Fulfilling);
                $order->failure_reason = null;
                $order->failed_at = null;
                $order->save();
            }

            $now = now();
            $inserted = Fulfillment::query()->insertOrIgnore([
                [
                    'order_id' => $order->id,
                    'status' => FulfillmentStatus::Pending->value,
                    'attempt' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

            /** @var Fulfillment $fulfillment */
            $fulfillment = Fulfillment::query()
                ->where('order_id', $order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $ownsCall = $inserted > 0;

            if (! $ownsCall) {
                if ($fulfillment->status === FulfillmentStatus::Succeeded) {
                    return [$order, $fulfillment, false];
                }

                $staleAfter = (int) config('commerce.fulfillment.stuck_after_seconds');
                $isStale = $fulfillment->updated_at === null
                    || $fulfillment->updated_at->lte(now()->subSeconds($staleAfter));

                $inFlightPending = $fulfillment->status === FulfillmentStatus::Pending && ! $isStale;

                if ($inFlightPending) {
                    return [$order, $fulfillment, false];
                }

                $fulfillment->attempt = $fulfillment->attempt + 1;
                $fulfillment->save();
                $ownsCall = true;
            }

            CommerceLog::event('fulfill_started', [
                'order_id' => $order->id,
                'fulfillment_id' => $fulfillment->id,
                'owns_call' => $ownsCall,
                'outcome' => 'fulfill_started',
            ]);

            return [$order, $fulfillment, $ownsCall];
        });
    }

    private function tryProvider(Order $order, Fulfillment $fulfillment, ProviderName $provider): void
    {
        $key = $provider->idempotencyKey($order->id);

        $fulfillment->provider = $provider;
        $fulfillment->idempotency_key = $key;
        $fulfillment->status = FulfillmentStatus::Pending;
        $fulfillment->save();

        $client = $this->providers->get($provider);

        try {
            $result = $client->fulfill($key, $order->sku);
        } catch (ProviderTimeoutException $e) {
            CommerceLog::event('timeout', [
                'order_id' => $order->id,
                'provider' => $provider->value,
                'idempotency_key' => $key,
                'outcome' => 'timeout',
            ]);

            $fulfillment->status = FulfillmentStatus::Unknown;
            $fulfillment->last_error = $e->getMessage();
            $fulfillment->save();

            $this->resolveUnknown($order, $fulfillment, $provider);

            return;
        }

        $this->applyResult($order, $fulfillment, $provider, $result);
    }

    private function resolveUnknown(Order $order, Fulfillment $fulfillment, ProviderName $provider): void
    {
        $key = $provider->idempotencyKey($order->id);
        $attempts = (int) config('commerce.fulfillment.status_attempts');
        $backoffMs = (int) config('commerce.fulfillment.status_backoff_ms');
        $client = $this->providers->get($provider);

        $result = ProviderResult::notFound();

        for ($i = 0; $i < $attempts; $i++) {
            if ($i > 0 && $backoffMs > 0) {
                usleep($backoffMs * 1000 * (2 ** ($i - 1)));
            }

            $result = $client->fetchStatus($key);

            CommerceLog::event('status_checked', [
                'order_id' => $order->id,
                'provider' => $provider->value,
                'idempotency_key' => $key,
                'provider_outcome' => $result->outcome,
                'attempt' => $i + 1,
                'outcome' => 'status_checked',
            ]);

            if (! $result->isNotFound()) {
                break;
            }
        }

        $this->applyResult($order, $fulfillment, $provider, $result);
    }

    private function applyResult(Order $order, Fulfillment $fulfillment, ProviderName $provider, ProviderResult $result): void
    {
        if ($result->isIssued()) {
            $this->completeDelivery($order, $fulfillment, $result->code);

            return;
        }

        if ($result->isFailed() || $result->isNotFound()) {
            $fallback = $provider->fallback();

            if ($fallback !== null) {
                CommerceLog::event('fallback', [
                    'order_id' => $order->id,
                    'from' => $provider->value,
                    'to' => $fallback->value,
                    'reason' => $result->outcome,
                    'outcome' => 'fallback',
                ]);

                $this->tryProvider($order, $fulfillment, $fallback);

                return;
            }
        }

        $this->failOrder($order, $fulfillment, $result->error ?? 'provider_exhausted');
    }

    private function completeDelivery(Order $order, Fulfillment $fulfillment, string $code): void
    {
        DB::transaction(function () use ($order, $fulfillment, $code): void {
            /** @var Order $order */
            $order = Order::query()->where('id', $order->id)->lockForUpdate()->firstOrFail();
            /** @var Fulfillment $fulfillment */
            $fulfillment = Fulfillment::query()->where('id', $fulfillment->id)->lockForUpdate()->firstOrFail();

            if ($fulfillment->status === FulfillmentStatus::Succeeded) {
                return;
            }

            $fulfillment->status = FulfillmentStatus::Succeeded;
            $fulfillment->code = Crypt::encryptString($code);
            $fulfillment->provider_ref = $fulfillment->idempotency_key;
            $fulfillment->last_error = null;
            $fulfillment->save();

            /** @var Product $product */
            $product = Product::query()->where('id', $order->product_id)->lockForUpdate()->firstOrFail();
            if ($product->stock_qty > 0) {
                $product->stock_qty -= 1;
                $product->save();
            }

            if ($order->status !== OrderStatus::Delivered) {
                if ($order->status === OrderStatus::Paid) {
                    $this->stateMachine->transition($order, OrderStatus::Fulfilling);
                }
                if ($order->status === OrderStatus::Fulfilling) {
                    $this->stateMachine->transition($order, OrderStatus::Delivered);
                }
            }

            $this->ledger->recordDelivery($order);

            CommerceLog::event('delivered', [
                'order_id' => $order->id,
                'provider' => $fulfillment->provider?->value,
                'idempotency_key' => $fulfillment->idempotency_key,
                'outcome' => 'delivered',
            ]);
        });
    }

    private function failOrder(Order $order, Fulfillment $fulfillment, string $reason): void
    {
        DB::transaction(function () use ($order, $fulfillment, $reason): void {
            /** @var Order $order */
            $order = Order::query()->where('id', $order->id)->lockForUpdate()->firstOrFail();
            /** @var Fulfillment $fulfillment */
            $fulfillment = Fulfillment::query()->where('id', $fulfillment->id)->lockForUpdate()->firstOrFail();

            if ($fulfillment->status === FulfillmentStatus::Succeeded) {
                return;
            }

            $fulfillment->status = FulfillmentStatus::Failed;
            $fulfillment->last_error = $reason;
            $fulfillment->save();

            if ($order->status === OrderStatus::Fulfilling) {
                $this->stateMachine->transition($order, OrderStatus::Failed, $reason);
            }

            CommerceLog::event('fulfill_failed', [
                'order_id' => $order->id,
                'provider' => $fulfillment->provider?->value,
                'idempotency_key' => $fulfillment->idempotency_key,
                'reason' => $reason,
                'outcome' => 'failed',
            ]);
        });
    }
}
