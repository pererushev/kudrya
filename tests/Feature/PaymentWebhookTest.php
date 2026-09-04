<?php

namespace Tests\Feature;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
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
        $order = $this->postJson('/api/orders', ['sku' => 'STEAM-CS2-KEY'])->json();

        $payload = $this->paidPayload($order['id'], 1499, 'evt-1');

        $this->postJson('/api/webhooks/payment', $payload)
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('duplicate', false);

        $paid = $this->getJson('/api/orders/'.$order['id'])->json();

        $this->assertSame(OrderStatus::Delivered->value, $paid['status']);
        $this->assertNotEmpty($paid['code']);
        $this->assertSame(9, Product::query()->where('sku', 'STEAM-CS2-KEY')->value('stock_qty'));
        $this->assertSame(1, Fulfillment::query()->count());
        $this->assertSame(1, ProviderIssuance::query()->count());
        $this->assertSame(0, (int) LedgerEntry::query()->sum('amount_cents'));
    }

    public function test_duplicate_event_id_does_not_reissue(): void
    {
        $this->seedProduct();
        $order = $this->postJson('/api/orders', ['sku' => 'STEAM-CS2-KEY'])->json();
        $payload = $this->paidPayload($order['id'], 1499, 'evt-dup');

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
        $order = $this->postJson('/api/orders', ['sku' => 'STEAM-CS2-KEY'])->json();

        $this->postJson('/api/webhooks/payment', $this->paidPayload($order['id'], 1499, 'evt-a'))->assertOk();
        $this->postJson('/api/webhooks/payment', $this->paidPayload($order['id'], 1499, 'evt-b'))
            ->assertOk()
            ->assertJsonPath('duplicate', true);

        $this->assertSame(OrderStatus::Delivered, Order::query()->find($order['id'])->status);
        $this->assertSame(1, Fulfillment::query()->count());
        $this->assertSame(1, ProviderIssuance::query()->count());
        $this->assertSame(2, PaymentEvent::query()->count());
        $this->assertSame(9, Product::query()->value('stock_qty'));
    }

    public function test_amount_mismatch_is_rejected(): void
    {
        $this->seedProduct();
        $order = $this->postJson('/api/orders', ['sku' => 'STEAM-CS2-KEY'])->json();

        $this->postJson('/api/webhooks/payment', $this->paidPayload($order['id'], 1, 'evt-bad'))
            ->assertStatus(422);

        $this->assertSame('pending_payment', $this->getJson('/api/orders/'.$order['id'])->json('status'));
        $this->assertDatabaseCount('payment_events', 0);
    }
}
