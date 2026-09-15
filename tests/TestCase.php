<?php

namespace Tests;

use App\Models\Category;
use App\Models\DigitalKey;
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function seedProduct(array $overrides = [], int $keys = 10): Product
    {
        $category = Category::query()->first() ?? Category::query()->create([
            'slug' => 'steam',
            'name' => 'Steam',
        ]);

        $product = Product::factory()->create(array_merge([
            'category_id' => $category->id,
            'sku' => 'STEAM-TOPUP-500',
            'title' => 'Пополнение Steam 500 ₽',
            'price_cents' => 50_000,
            'stock_qty' => 10,
            'sort_rank' => 100,
            'is_available' => true,
        ], $overrides));

        if ($keys > 0) {
            $this->seedKeys($keys);
        }

        return $product;
    }

    protected function seedKeys(int $count = 10): void
    {
        $codes = require database_path('data/digital_keys.php');

        foreach (array_slice($codes, 0, $count) as $code) {
            DigitalKey::query()->updateOrCreate(['code' => $code], ['code' => $code]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function paidPayload(string $orderId, int $amountRubles = 500, ?string $eventId = null): array
    {
        return [
            'event_id' => $eventId ?? 'evt-'.uniqid('', true),
            'order_id' => $orderId,
            'amount' => $amountRubles,
            'currency' => 'RUB',
            'created_at' => '2025-01-01T12:00:00Z',
            'status' => 'paid',
        ];
    }
}
