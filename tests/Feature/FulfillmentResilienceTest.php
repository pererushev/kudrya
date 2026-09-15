<?php

namespace Tests\Feature;

use App\Domain\FulfillmentService;
use App\Enums\OrderStatus;
use App\Models\DigitalKey;
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

        $order = $this->postJson('/api/orders', ['sku' => 'STEAM-TOPUP-500'])->json();
        $this->postJson('/api/webhooks/payment', $this->paidPayload($order['id']))->assertOk();

        $paid = $this->getJson('/api/orders/'.$order['id'])->json();

        $this->assertSame(OrderStatus::Delivered->value, $paid['status']);
        $this->assertNotEmpty($paid['code']);
        $this->assertSame('a', $paid['provider']);
        $this->assertSame(1, ProviderIssuance::query()->count());
        $this->assertSame(1, Fulfillment::query()->count());
        $this->assertSame(1, DigitalKey::query()->whereNotNull('order_id')->count());
        $this->assertSame($paid['code'], DigitalKey::query()->where('order_id', $order['id'])->value('code'));
    }

    public function test_definitive_fail_on_a_falls_back_to_b(): void
    {
        $this->seedProduct();
        ChaosDigitalGoodsProvider::forceNext('failed');

        $order = $this->postJson('/api/orders', ['sku' => 'STEAM-TOPUP-500'])->json();
        $this->postJson('/api/webhooks/payment', $this->paidPayload($order['id']))->assertOk();

        $paid = $this->getJson('/api/orders/'.$order['id'])->json();

        $this->assertSame(OrderStatus::Delivered->value, $paid['status']);
        $this->assertSame('b', $paid['provider']);
        $this->assertSame(1, DigitalKey::query()->whereNotNull('order_id')->count());
        $this->assertSame(1, ProviderIssuance::query()->where('provider', 'b')->count());
        $this->assertSame(0, ProviderIssuance::query()->where('provider', 'a')->count());
    }

    public function test_timeout_then_same_key_retry_does_not_mint_a_second_code(): void
    {
        $this->seedProduct();
        ChaosDigitalGoodsProvider::forceSequence(['timeout']);

        $order = $this->postJson('/api/orders', ['sku' => 'STEAM-TOPUP-500'])->json();
        $this->postJson('/api/webhooks/payment', $this->paidPayload($order['id']))->assertOk();
        $first = $this->getJson('/api/orders/'.$order['id'])->json('code');

        $this->assertSame(1, ProviderIssuance::query()->count());
        $this->assertSame($first, ProviderIssuance::query()->first()->code);
        $this->assertSame(1, DigitalKey::query()->whereNotNull('allocated_at')->count());
    }

    public function test_both_providers_fail_marks_delivery_failed(): void
    {
        $this->seedProduct();
        ChaosDigitalGoodsProvider::forceSequence(['failed', 'failed']);

        $order = $this->postJson('/api/orders', ['sku' => 'STEAM-TOPUP-500'])->json();
        $this->postJson('/api/webhooks/payment', $this->paidPayload($order['id']))->assertOk();

        $paid = $this->getJson('/api/orders/'.$order['id'])->json();

        $this->assertSame(OrderStatus::DeliveryFailed->value, $paid['status']);
        $this->assertNull($paid['code']);
        $this->assertSame(10, Product::query()->value('stock_qty'));
    }

    public function test_empty_key_pool_is_out_of_stock_not_a_crash(): void
    {
        $this->seedProduct([], 0);

        $order = $this->postJson('/api/orders', ['sku' => 'STEAM-TOPUP-500'])->json();
        $this->postJson('/api/webhooks/payment', $this->paidPayload($order['id']))
            ->assertOk();

        $paid = $this->getJson('/api/orders/'.$order['id'])->json();
        $this->assertSame(OrderStatus::OutOfStock->value, $paid['status']);
        $this->assertNull($paid['code']);
    }

    public function test_out_of_stock_recovers_after_keys_are_added(): void
    {
        $this->seedProduct([], 0);

        $order = $this->postJson('/api/orders', ['sku' => 'STEAM-TOPUP-500'])->json();
        $this->postJson('/api/webhooks/payment', $this->paidPayload($order['id']))->assertOk();
        $this->assertSame('out_of_stock', $this->getJson('/api/orders/'.$order['id'])->json('status'));

        $this->seedKeys(3);
        app(FulfillmentService::class)->fulfill($order['id']);

        $paid = $this->getJson('/api/orders/'.$order['id'])->json();
        $this->assertSame('delivered', $paid['status']);
        $this->assertNotEmpty($paid['code']);
    }
}
