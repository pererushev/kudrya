<?php

namespace App\Enums;

enum LedgerReason: string
{
    case Payment = 'payment';
    case Delivery = 'delivery';
}
