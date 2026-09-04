<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $stock = fake()->numberBetween(1, 20);

        return [
            'sku' => strtoupper(fake()->unique()->bothify('SKU-#####')),
            'title' => fake()->words(3, true),
            'category_id' => Category::query()->first()?->id ?? Category::query()->create([
                'slug' => 'steam',
                'name' => 'Steam',
            ])->id,
            'sort_rank' => fake()->numberBetween(1, 10_000),
            'price_cents' => fake()->numberBetween(199, 9999),
            'stock_qty' => $stock,
            'is_available' => $stock > 0,
        ];
    }
}
