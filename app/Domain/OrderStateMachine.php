<?php

namespace App\Domain;

use App\Enums\OrderStatus;
use App\Models\Order;
use DomainException;

class OrderStateMachine
{
    /**
     * @var array<string, list<OrderStatus>>
     */
    private const ALLOWED = [
        OrderStatus::PendingPayment->value => [OrderStatus::Paid],
        OrderStatus::Paid->value => [OrderStatus::Fulfilling],
        OrderStatus::Fulfilling->value => [OrderStatus::Delivered, OrderStatus::Failed],
        OrderStatus::Delivered->value => [],
        OrderStatus::Failed->value => [OrderStatus::Fulfilling],
    ];

    public function canTransition(Order $order, OrderStatus $to): bool
    {
        return in_array($to, self::ALLOWED[$order->status->value] ?? [], true);
    }

    public function transition(Order $order, OrderStatus $to, ?string $failureReason = null): Order
    {
        if (! $this->canTransition($order, $to)) {
            throw new DomainException(sprintf(
                'Invalid order transition %s → %s for %s',
                $order->status->value,
                $to->value,
                $order->id,
            ));
        }

        $order->status = $to;
        $order->version = $order->version + 1;

        match ($to) {
            OrderStatus::Paid => $order->paid_at = now(),
            OrderStatus::Delivered => $order->delivered_at = now(),
            OrderStatus::Failed => tap($order, function (Order $order) use ($failureReason): void {
                $order->failed_at = now();
                $order->failure_reason = $failureReason;
            }),
            default => null,
        };

        $order->save();

        return $order;
    }
}
