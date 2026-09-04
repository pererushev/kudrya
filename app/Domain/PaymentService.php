<?php

namespace App\Domain;

use App\Enums\OrderStatus;
use App\Jobs\FulfillOrder;
use App\Models\Order;
use App\Models\PaymentEvent;
use App\Support\CommerceLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        private readonly OrderStateMachine $stateMachine,
        private readonly LedgerService $ledger,
    ) {}

    /**
     * @param  array{event_id: string, order_id: string, amount_cents: int, status: string}  $payload
     * @return array{ok: bool, duplicate: bool}
     */
    public function handlePaidWebhook(array $payload): array
    {
        if (($payload['status'] ?? '') !== 'paid') {
            throw ValidationException::withMessages([
                'status' => 'Only paid webhooks are accepted.',
            ]);
        }

        $dispatch = false;
        $duplicate = false;

        DB::transaction(function () use ($payload, &$dispatch, &$duplicate): void {
            /** @var Order|null $order */
            $order = Order::query()
                ->where('id', $payload['order_id'])
                ->lockForUpdate()
                ->first();

            if ($order === null) {
                throw ValidationException::withMessages([
                    'order_id' => 'Order not found.',
                ]);
            }

            if ($order->amount_cents !== (int) $payload['amount_cents']) {
                throw ValidationException::withMessages([
                    'amount_cents' => 'Amount does not match the order.',
                ]);
            }

            $now = now();
            $inserted = PaymentEvent::query()->insertOrIgnore([
                [
                    'event_id' => $payload['event_id'],
                    'order_id' => $order->id,
                    'amount_cents' => $payload['amount_cents'],
                    'status' => $payload['status'],
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

            if ($order->status !== OrderStatus::PendingPayment) {
                $duplicate = true;
                CommerceLog::event('duplicate_payment_for_order', [
                    'event_id' => $payload['event_id'],
                    'order_id' => $order->id,
                    'status' => $order->status->value,
                    'outcome' => 'duplicate_webhook',
                ]);

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
}
