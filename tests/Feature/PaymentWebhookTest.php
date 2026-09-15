<?php

namespace Tests\Feature;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Models\DigitalKey;
use App\Models\Fulfillment;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\PaymentEvent;
use App\Models\Product;
use App\Models\ProviderIssuance;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    public function test_payment_delivers_code_exactly_once(): void
    {
        $this->seedProduct();
        $order = $this->postJson('/api/orders', ['sku' => 'STEAM-TOPUP-500'])->json();

        $payload = $this->paidPayload($order['id'], 500, 'evt-1');

        $this->postJson('/api/webhooks/payment', $payload)
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('duplicate', false);

        $paid = $this->getJson('/api/orders/'.$order['id'])->json();

        $this->assertSame(OrderStatus::Delivered->value, $paid['status']);
        $this->assertNotEmpty($paid['code']);
        $this->assertTrue(DigitalKey::query()->where('code', $paid['code'])->where('order_id', $order['id'])->exists());
        $this->assertSame(9, Product::query()->where('sku', 'STEAM-TOPUP-500')->value('stock_qty'));
        $this->assertSame(1, Fulfillment::query()->count());
        $this->assertSame(1, ProviderIssuance::query()->count());
        $this->assertSame(0, (int) LedgerEntry::query()->sum('amount_cents'));
    }

    public function test_duplicate_event_id_does_not_reissue(): void
    {
        $this->seedProduct();
        $order = $this->postJson('/api/orders', ['sku' => 'STEAM-TOPUP-500'])->json();
        $payload = $this->paidPayload($order['id'], 500, 'evt-dup');

        $this->postJson('/api/webhooks/payment', $payload)->assertOk();
        $first = $this->getJson('/api/orders/'.$order['id'])->json();

        $this->postJson('/api/webhooks/payment', $payload)
            ->assertOk()
            ->assertJsonPath('duplicate', true);

        $second = $this->getJson('/api/orders/'.$order['id'])->json();

        $this->assertSame($first['code'], $second['code']);
        $this->assertSame(1, PaymentEvent::query()->count());
        $this->assertSame(1, Fulfillment::query()->where('status', FulfillmentStatus::Succeeded)->count());
        $this->assertSame(1, ProviderIssuance::query()->count());
        $this->assertSame(9, Product::query()->value('stock_qty'));
    }

    public function test_parallel_distinct_event_ids_still_issue_once(): void
    {
        $this->seedProduct();
        $order = $this->postJson('/api/orders', ['sku' => 'STEAM-TOPUP-500'])->json();

        $this->postJson('/api/webhooks/payment', $this->paidPayload($order['id'], 500, 'evt-a'))->assertOk();
        $this->postJson('/api/webhooks/payment', $this->paidPayload($order['id'], 500, 'evt-b'))
            ->assertOk()
            ->assertJsonPath('duplicate', true);

        $this->assertSame(OrderStatus::Delivered, Order::query()->find($order['id'])->status);
        $this->assertSame(1, Fulfillment::query()->count());
        $this->assertSame(1, ProviderIssuance::query()->count());
        $this->assertSame(2, PaymentEvent::query()->count());
        $this->assertSame(9, Product::query()->value('stock_qty'));
        $this->assertSame(1, DigitalKey::query()->whereNotNull('order_id')->count());
    }

    public function test_amount_mismatch_is_rejected(): void
    {
        $this->seedProduct();
        $order = $this->postJson('/api/orders', ['sku' => 'STEAM-TOPUP-500'])->json();

        $this->postJson('/api/webhooks/payment', $this->paidPayload($order['id'], 1, 'evt-bad'))
            ->assertStatus(422);

        $this->assertSame('created', $this->getJson('/api/orders/'.$order['id'])->json('status'));
        $this->assertDatabaseCount('payment_events', 0);
    }

    public function test_webhook_before_order_returns_503(): void
    {
        $this->postJson('/api/webhooks/payment', $this->paidPayload('ord_missing', 500, 'evt-early'))
            ->assertStatus(503);

        $this->assertDatabaseCount('payment_events', 0);
    }

    public function test_failed_webhook_then_paid_still_delivers(): void
    {
        $this->seedProduct();
        $order = $this->postJson('/api/orders', ['sku' => 'STEAM-TOPUP-500'])->json();

        $this->postJson('/api/webhooks/payment', [
            'event_id' => 'evt-fail',
            'order_id' => $order['id'],
            'status' => 'failed',
            'amount' => 500,
            'currency' => 'RUB',
            'created_at' => '2025-01-01T12:00:00Z',
        ])->assertOk();

        $this->assertSame('payment_failed', $this->getJson('/api/orders/'.$order['id'])->json('status'));

        $this->postJson('/api/webhooks/payment', $this->paidPayload($order['id'], 500, 'evt-paid-later'))
            ->assertOk()
            ->assertJsonPath('duplicate', false);

        $paid = $this->getJson('/api/orders/'.$order['id'])->json();
        $this->assertSame('delivered', $paid['status']);
        $this->assertNotEmpty($paid['code']);
    }

    public function test_paid_then_failed_does_not_reverse(): void
    {
        $this->seedProduct();
        $order = $this->postJson('/api/orders', ['sku' => 'STEAM-TOPUP-500'])->json();
        $this->postJson('/api/webhooks/payment', $this->paidPayload($order['id'], 500, 'evt-paid'))->assertOk();

        $this->postJson('/api/webhooks/payment', [
            'event_id' => 'evt-fail-late',
            'order_id' => $order['id'],
            'status' => 'failed',
            'amount' => 500,
            'currency' => 'RUB',
        ])->assertOk();

        $this->assertSame('delivered', $this->getJson('/api/orders/'.$order['id'])->json('status'));
    }

    public function test_legacy_amount_cents_payload_still_works(): void
    {
        $this->seedProduct();
        $order = $this->postJson('/api/orders', ['sku' => 'STEAM-TOPUP-500'])->json();

        $this->postJson('/api/webhooks/payment', [
            'event_id' => 'evt-cents',
            'order_id' => $order['id'],
            'amount_cents' => 50_000,
            'status' => 'paid',
        ])->assertOk();

        $this->assertSame('delivered', $this->getJson('/api/orders/'.$order['id'])->json('status'));
    }
}
