<?php

namespace Tests\Feature;

use App\Domain\FulfillmentService;
use App\Enums\OrderStatus;
use App\Jobs\FulfillOrder;
use App\Models\Fulfillment;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ReconciliationTest extends TestCase
{
    public function test_report_finds_paid_not_delivered_when_job_did_not_run(): void
    {
        $this->seedProduct();
        Bus::fake([FulfillOrder::class]);

        $order = $this->postJson('/api/orders', ['sku' => 'STEAM-CS2-KEY'])->json();
        $this->postJson('/api/webhooks/payment', $this->paidPayload($order['id'], 1499))->assertOk();

        $this->assertSame(OrderStatus::Paid->value, $this->getJson('/api/orders/'.$order['id'])->json('status'));

        $report = $this->getJson('/api/admin/reconciliation')->json();

        $this->assertNotEmpty($report['paid_not_delivered']);
        $this->assertSame($order['id'], $report['paid_not_delivered'][0]['order_id']);
        $this->assertEmpty($report['ledger_unbalanced']);
        $this->assertEmpty($report['delivered_not_paid']);
    }

    public function test_recover_runs_fulfillment_for_paid_order_without_code(): void
    {
        $this->seedProduct();
        Bus::fake([FulfillOrder::class]);

        $order = $this->postJson('/api/orders', ['sku' => 'STEAM-CS2-KEY'])->json();
        $this->postJson('/api/webhooks/payment', $this->paidPayload($order['id'], 1499))->assertOk();

        app(FulfillmentService::class)->fulfill($order['id']);

        $paid = $this->getJson('/api/orders/'.$order['id'])->json();
        $this->assertSame(OrderStatus::Delivered->value, $paid['status']);
        $this->assertNotEmpty($paid['code']);
        $this->assertSame(1, Fulfillment::query()->count());
    }

    public function test_artisan_reconcile_command(): void
    {
        $this->seedProduct();
        $this->artisan('orders:reconcile')->assertSuccessful();
    }
}
