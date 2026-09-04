<?php

namespace App\Domain;

use App\Enums\LedgerAccount;
use App\Enums\LedgerReason;
use App\Models\LedgerEntry;
use App\Models\Order;

class LedgerService
{
    public function recordPayment(Order $order): void
    {
        $this->postPair(
            $order,
            LedgerAccount::PlatformCash,
            LedgerAccount::CustomerClearing,
            $order->amount_cents,
            LedgerReason::Payment,
        );
    }

    public function recordDelivery(Order $order): void
    {
        $this->postPair(
            $order,
            LedgerAccount::CustomerClearing,
            LedgerAccount::CogsCodes,
            $order->amount_cents,
            LedgerReason::Delivery,
        );
    }

    private function postPair(
        Order $order,
        LedgerAccount $debit,
        LedgerAccount $credit,
        int $amount,
        LedgerReason $reason,
    ): void {
        $now = now();

        LedgerEntry::query()->insert([
            [
                'order_id' => $order->id,
                'account' => $debit->value,
                'amount_cents' => $amount,
                'reason' => $reason->value,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'order_id' => $order->id,
                'account' => $credit->value,
                'amount_cents' => -$amount,
                'reason' => $reason->value,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
