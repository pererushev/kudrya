<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case Fulfilling = 'fulfilling';
    case Delivered = 'delivered';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return $this === self::Delivered || $this === self::Failed;
    }

    public function allowsFulfillment(): bool
    {
        return in_array($this, [self::Paid, self::Fulfilling], true);
    }
}
