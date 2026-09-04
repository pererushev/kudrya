<?php

namespace Tests\Feature;

use App\Models\Category;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    public function test_storefront_lists_available_skus_only(): void
    {
        $steam = Category::query()->create(['slug' => 'steam', 'name' => 'Steam']);
        $this->seedProduct([
            'category_id' => $steam->id,
            'sku' => 'HOT-1',
            'sort_rank' => 500,
            'stock_qty' => 3,
        ]);
        $this->seedProduct([
            'category_id' => $steam->id,
            'sku' => 'HOT-2',
            'sort_rank' => 900,
            'stock_qty' => 8,
        ]);
        $this->seedProduct([
            'category_id' => $steam->id,
            'sku' => 'COLD-0',
            'sort_rank' => 1000,
            'stock_qty' => 0,
            'is_available' => false,
        ]);

        $response = $this->getJson('/api/storefront?category=steam');

        $response->assertOk()
            ->assertJsonPath('category', 'steam');

        $skus = array_column($response->json('items'), 'sku');
        $this->assertSame(['HOT-2', 'HOT-1'], $skus);
    }

    public function test_unknown_category_is_404(): void
    {
        $this->getJson('/api/storefront?category=nope')->assertNotFound();
    }
}
