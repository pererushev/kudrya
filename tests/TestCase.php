<?php

namespace Tests;

use App\Models\Category;
use App\Models\Product;
use App\Providers\DigitalGoods\ChaosDigitalGoodsProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ChaosDigitalGoodsProvider::forceSequence([]);
    }

    protected function seedProduct(array $overrides = []): Product
    {
        $category = Category::query()->first() ?? Category::query()->create([
            'slug' => 'steam',
            'name' => 'Steam',
        ]);

        return Product::factory()->create(array_merge([
            'category_id' => $category->id,
            'sku' => 'STEAM-CS2-KEY',
            'title' => 'Counter-Strike 2 Prime Key',
            'price_cents' => 1499,
            'stock_qty' => 10,
            'sort_rank' => 100,
            'is_available' => true,
        ], $overrides));
    }

    /**
     * @return array{event_id: string, order_id: string, amount_cents: int, status: string}
     */
    protected function paidPayload(string $orderId, int $amountCents, ?string $eventId = null): array
    {
        return [
            'event_id' => $eventId ?? 'evt-'.uniqid('', true),
            'order_id' => $orderId,
            'amount_cents' => $amountCents,
            'status' => 'paid',
        ];
    }
}
