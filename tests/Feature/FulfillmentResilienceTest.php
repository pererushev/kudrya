<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Fulfillment;
use App\Models\Product;
use App\Models\ProviderIssuance;
use App\Providers\DigitalGoods\ChaosDigitalGoodsProvider;
use Tests\TestCase;

class FulfillmentResilienceTest extends TestCase
{
    public function test_timeout_is_not_a_failure_and_does_not_double_issue(): void
    {
        $this->seedProduct();
        ChaosDigitalGoodsProvider::forceNext('timeout');

        $order = $this->postJson('/api/orders', ['sku' => 'STEAM-CS2-KEY'])->json();
        $this->postJson('/api/webhooks/payment', $this->paidPayload($order['id'], 1499))->assertOk();

        $paid = $this->getJson('/api/orders/'.$order['id'])->json();

        $this->assertSame(OrderStatus::Delivered->value, $paid['status']);
        $this->assertNotEmpty($paid['code']);
        $this->assertSame('a', $paid['provider']);
        $this->assertSame(1, ProviderIssuance::query()->count());
        $this->assertSame(1, Fulfillment::query()->count());
        $this->assertStringStartsWith('A-STEAM-CS2-KEY-', $paid['code']);
    }

    public function test_definitive_fail_on_a_falls_back_to_b(): void
    {
        $this->seedProduct();
        ChaosDigitalGoodsProvider::forceNext('failed');

        $order = $this->postJson('/api/orders', ['sku' => 'STEAM-CS2-KEY'])->json();
        $this->postJson('/api/webhooks/payment', $this->paidPayload($order['id'], 1499))->assertOk();

        $paid = $this->getJson('/api/orders/'.$order['id'])->json();

        $this->assertSame(OrderStatus::Delivered->value, $paid['status']);
        $this->assertSame('b', $paid['provider']);
        $this->assertStringStartsWith('B-STEAM-CS2-KEY-', $paid['code']);
        $this->assertSame(2, ProviderIssuance::query()->count());
    }

    public function test_timeout_then_same_key_retry_does_not_mint_a_second_code(): void
    {
        $this->seedProduct();
        ChaosDigitalGoodsProvider::forceSequence(['timeout']);

        $order = $this->postJson('/api/orders', ['sku' => 'STEAM-CS2-KEY'])->json();
        $this->postJson('/api/webhooks/payment', $this->paidPayload($order['id'], 1499))->assertOk();
        $first = $this->getJson('/api/orders/'.$order['id'])->json('code');

        $this->assertSame(1, ProviderIssuance::query()->count());
        $this->assertSame(
            $first,
            ProviderIssuance::query()->first()->code,
        );
    }

    public function test_both_providers_fail_marks_order_failed(): void
    {
        $this->seedProduct();
        ChaosDigitalGoodsProvider::forceSequence(['failed', 'failed']);

        $order = $this->postJson('/api/orders', ['sku' => 'STEAM-CS2-KEY'])->json();
        $this->postJson('/api/webhooks/payment', $this->paidPayload($order['id'], 1499))->assertOk();

        $paid = $this->getJson('/api/orders/'.$order['id'])->json();

        $this->assertSame(OrderStatus::Failed->value, $paid['status']);
        $this->assertNull($paid['code']);
        $this->assertSame(10, Product::query()->value('stock_qty'));
    }
}
