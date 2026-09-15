<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Created = 'created';
    case Paid = 'paid';
    case Delivering = 'delivering';
    case Delivered = 'delivered';
    case PaymentFailed = 'payment_failed';
    case OutOfStock = 'out_of_stock';
    case DeliveryFailed = 'delivery_failed';

    public function isTerminal(): bool
    {
        return $this === self::Delivered || $this === self::PaymentFailed;
    }

    public function allowsFulfillment(): bool
    {
        return in_array($this, [
            self::Paid,
            self::Delivering,
            self::OutOfStock,
            self::DeliveryFailed,
        ], true);
    }

    public function isPaidNotDelivered(): bool
    {
        return in_array($this, [
            self::Paid,
            self::Delivering,
            self::OutOfStock,
            self::DeliveryFailed,
        ], true);
    }
}
