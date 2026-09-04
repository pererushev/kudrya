<?php

namespace App\Jobs;

use App\Domain\ReconciliationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ReconcileStuckOrders implements ShouldQueue
{
    use Queueable;

    public function handle(ReconciliationService $reconciliation): void
    {
        $reconciliation->recoverStuck();
    }
}
