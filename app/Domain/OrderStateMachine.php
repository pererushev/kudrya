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
        OrderStatus::Created->value => [OrderStatus::Paid, OrderStatus::PaymentFailed],
        OrderStatus::Paid->value => [OrderStatus::Delivering],
        OrderStatus::Delivering->value => [
            OrderStatus::Delivered,
            OrderStatus::OutOfStock,
            OrderStatus::DeliveryFailed,
        ],
        OrderStatus::OutOfStock->value => [OrderStatus::Delivering],
        OrderStatus::DeliveryFailed->value => [OrderStatus::Delivering],
        OrderStatus::PaymentFailed->value => [OrderStatus::Paid],
        OrderStatus::Delivered->value => [],
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
            OrderStatus::PaymentFailed,
            OrderStatus::OutOfStock,
            OrderStatus::DeliveryFailed => tap($order, function (Order $order) use ($failureReason): void {
                $order->failed_at = now();
                $order->failure_reason = $failureReason;
            }),
            OrderStatus::Delivering => tap($order, function (Order $order): void {
                $order->failure_reason = null;
                $order->failed_at = null;
            }),
            default => null,
        };

        $order->save();

        return $order;
    }
}
