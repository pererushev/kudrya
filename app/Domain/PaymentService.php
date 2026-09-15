<?php

namespace App\Domain;

use App\Enums\OrderStatus;
use App\Jobs\FulfillOrder;
use App\Models\Order;
use App\Models\PaymentEvent;
use App\Support\CommerceLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PaymentService
{
    public function __construct(
        private readonly OrderStateMachine $stateMachine,
        private readonly LedgerService $ledger,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, duplicate: bool}
     */
    public function handleWebhook(array $payload): array
    {
        $status = (string) ($payload['status'] ?? '');
        if (! in_array($status, ['paid', 'failed'], true)) {
            throw ValidationException::withMessages([
                'status' => 'status must be paid or failed.',
            ]);
        }

        $dispatch = false;
        $duplicate = false;

        DB::transaction(function () use ($payload, $status, &$dispatch, &$duplicate): void {
            if (! Str::isUuid((string) $payload['order_id'])) {
                throw new HttpException(503, 'Order not found.');
            }

            /** @var Order|null $order */
            $order = Order::query()
                ->where('id', $payload['order_id'])
                ->lockForUpdate()
                ->first();

            if ($order === null) {
                throw new HttpException(503, 'Order not found.');
            }

            $amountCents = $this->amountCents($payload);

            if ($status === 'paid') {
                if ($amountCents === null) {
                    throw ValidationException::withMessages([
                        'amount' => 'amount or amount_cents is required.',
                    ]);
                }
                if ($order->amount_cents !== $amountCents) {
                    throw ValidationException::withMessages([
                        'amount' => 'Amount does not match the order.',
                    ]);
                }
            }

            $now = now();
            $inserted = PaymentEvent::query()->insertOrIgnore([
                [
                    'event_id' => $payload['event_id'],
                    'order_id' => $order->id,
                    'amount_cents' => $amountCents ?? $order->amount_cents,
                    'status' => $status,
                    'payload' => json_encode($payload),
                    'received_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

            if ($inserted === 0) {
                $duplicate = true;
                CommerceLog::event('duplicate_webhook', [
                    'event_id' => $payload['event_id'],
                    'order_id' => $order->id,
                    'outcome' => 'duplicate_webhook',
                ]);

                return;
            }

            if ($status === 'failed') {
                if ($order->status === OrderStatus::Created) {
                    $this->stateMachine->transition($order, OrderStatus::PaymentFailed, 'payment_failed');
                    CommerceLog::event('payment_failed', [
                        'event_id' => $payload['event_id'],
                        'order_id' => $order->id,
                        'outcome' => 'payment_failed',
                    ]);
                } else {
                    $duplicate = true;
                    CommerceLog::event('ignored_failed_webhook', [
                        'event_id' => $payload['event_id'],
                        'order_id' => $order->id,
                        'status' => $order->status->value,
                        'outcome' => 'ignored_failed_webhook',
                    ]);
                }

                return;
            }

            if ($order->status === OrderStatus::Delivered
                || $order->status->isPaidNotDelivered()) {
                $duplicate = true;
                CommerceLog::event('duplicate_payment_for_order', [
                    'event_id' => $payload['event_id'],
                    'order_id' => $order->id,
                    'status' => $order->status->value,
                    'outcome' => 'duplicate_webhook',
                ]);

                return;
            }

            if ($order->status === OrderStatus::PaymentFailed) {
                $this->stateMachine->transition($order, OrderStatus::Paid);
                $this->ledger->recordPayment($order);
                $dispatch = true;
                CommerceLog::event('paid', [
                    'event_id' => $payload['event_id'],
                    'order_id' => $order->id,
                    'amount_cents' => $order->amount_cents,
                    'outcome' => 'paid_after_failed',
                ]);

                return;
            }

            if ($order->status !== OrderStatus::Created) {
                $duplicate = true;

                return;
            }

            $this->stateMachine->transition($order, OrderStatus::Paid);
            $this->ledger->recordPayment($order);
            $dispatch = true;

            CommerceLog::event('paid', [
                'event_id' => $payload['event_id'],
                'order_id' => $order->id,
                'amount_cents' => $order->amount_cents,
                'outcome' => 'paid',
            ]);
        });

        if ($dispatch) {
            FulfillOrder::dispatch($payload['order_id']);
        }

        return ['ok' => true, 'duplicate' => $duplicate];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function amountCents(array $payload): ?int
    {
        if (array_key_exists('amount', $payload) && $payload['amount'] !== null && $payload['amount'] !== '') {
            return (int) round(((float) $payload['amount']) * 100);
        }

        if (array_key_exists('amount_cents', $payload) && $payload['amount_cents'] !== null && $payload['amount_cents'] !== '') {
            return (int) $payload['amount_cents'];
        }

        return null;
    }
}
