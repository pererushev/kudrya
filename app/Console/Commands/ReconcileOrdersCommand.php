<?php

namespace App\Console\Commands;

use App\Domain\ReconciliationService;
use Illuminate\Console\Command;

class ReconcileOrdersCommand extends Command
{
    protected $signature = 'orders:reconcile {--recover : Re-dispatch stuck fulfillments}';

    protected $description = 'Report paid-not-delivered / delivered-not-paid / ledger mismatches';

    public function handle(ReconciliationService $reconciliation): int
    {
        $report = $reconciliation->report();

        $this->info('Paid, not delivered: '.count($report['paid_not_delivered']));
        $this->info('Delivered, not paid: '.count($report['delivered_not_paid']));
        $this->info('Ledger unbalanced: '.count($report['ledger_unbalanced']));
        $this->info('Stuck: '.count($report['stuck']));

        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        if ($this->option('recover')) {
            $ids = $reconciliation->recoverStuck();
            $this->info('Recovered: '.count($ids));
        }

        return self::SUCCESS;
    }
}
