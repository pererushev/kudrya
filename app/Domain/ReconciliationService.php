<?php

namespace App\Domain;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Jobs\FulfillOrder;
use App\Models\Fulfillment;
use App\Models\LedgerEntry;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class ReconciliationService
{
    /**
     * @return array{
     *     paid_not_delivered: list<array<string, mixed>>,
     *     delivered_not_paid: list<array<string, mixed>>,
     *     ledger_unbalanced: list<array<string, mixed>>,
     *     stuck: list<array<string, mixed>>
     * }
     */
    public function report(): array
    {
        $paidNotDelivered = Order::query()
            ->whereIn('status', [OrderStatus::Paid, OrderStatus::Fulfilling, OrderStatus::Failed])
            ->whereDoesntHave('fulfillment', fn ($q) => $q->where('status', FulfillmentStatus::Succeeded))
            ->get(['id', 'sku', 'status', 'amount_cents', 'updated_at'])
            ->map(fn (Order $order) => [
                'order_id' => $order->id,
                'sku' => $order->sku,
                'status' => $order->status->value,
                'amount_cents' => $order->amount_cents,
                'updated_at' => $order->updated_at?->toIso8601String(),
            ])
            ->all();

        $deliveredNotPaid = Order::query()
            ->where('status', OrderStatus::Delivered)
            ->whereNull('paid_at')
            ->get(['id', 'sku', 'status', 'amount_cents'])
            ->map(fn (Order $order) => [
                'order_id' => $order->id,
                'sku' => $order->sku,
                'status' => $order->status->value,
                'amount_cents' => $order->amount_cents,
            ])
            ->all();

        $succeededUnpaid = Fulfillment::query()
            ->where('status', FulfillmentStatus::Succeeded)
            ->whereHas('order', fn ($q) => $q->whereNull('paid_at'))
            ->get()
            ->map(fn (Fulfillment $f) => [
                'order_id' => $f->order_id,
                'fulfillment_id' => $f->id,
            ])
            ->all();

        $ledgerUnbalanced = DB::table('ledger_entries')
            ->select('order_id', DB::raw('SUM(amount_cents) as net'))
            ->groupBy('order_id')
            ->havingRaw('SUM(amount_cents) <> 0')
            ->get()
            ->map(fn ($row) => [
                'order_id' => $row->order_id,
                'net_cents' => (int) $row->net,
            ])
            ->all();

        $stuckAfter = (int) config('commerce.fulfillment.stuck_after_seconds');

        $stuck = Order::query()
            ->whereIn('status', [OrderStatus::Paid, OrderStatus::Fulfilling])
            ->where('updated_at', '<=', now()->subSeconds($stuckAfter))
            ->get(['id', 'sku', 'status', 'updated_at'])
            ->map(fn (Order $order) => [
                'order_id' => $order->id,
                'sku' => $order->sku,
                'status' => $order->status->value,
                'updated_at' => $order->updated_at?->toIso8601String(),
            ])
            ->all();

        return [
            'paid_not_delivered' => $paidNotDelivered,
            'delivered_not_paid' => array_values(array_merge($deliveredNotPaid, $succeededUnpaid)),
            'ledger_unbalanced' => $ledgerUnbalanced,
            'stuck' => $stuck,
            'ledger_zero_orders' => LedgerEntry::query()->distinct('order_id')->count('order_id'),
        ];
    }

    /**
     * @return list<string> recovered order ids
     */
    public function recoverStuck(): array
    {
        $stuckAfter = (int) config('commerce.fulfillment.stuck_after_seconds');

        $ids = Order::query()
            ->whereIn('status', [OrderStatus::Paid, OrderStatus::Fulfilling])
            ->where('updated_at', '<=', now()->subSeconds($stuckAfter))
            ->pluck('id')
            ->all();

        $unknown = Fulfillment::query()
            ->where('status', FulfillmentStatus::Unknown)
            ->pluck('order_id')
            ->all();

        $ids = array_values(array_unique([...$ids, ...$unknown]));

        foreach ($ids as $id) {
            FulfillOrder::dispatch($id);
        }

        return $ids;
    }
}
