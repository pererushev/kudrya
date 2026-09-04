<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['slug' => 'steam', 'name' => 'Steam'],
            ['slug' => 'playstation', 'name' => 'PlayStation'],
            ['slug' => 'xbox', 'name' => 'Xbox'],
            ['slug' => 'nintendo', 'name' => 'Nintendo'],
            ['slug' => 'mobile', 'name' => 'Mobile'],
        ];

        foreach ($categories as $row) {
            Category::query()->updateOrCreate(['slug' => $row['slug']], $row);
        }

        $bySlug = Category::query()->pluck('id', 'slug');

        Product::query()->updateOrCreate(
            ['sku' => 'STEAM-CS2-KEY'],
            [
                'title' => 'Counter-Strike 2 Prime Key',
                'category_id' => $bySlug['steam'],
                'sort_rank' => 10_000,
                'price_cents' => 1499,
                'stock_qty' => 50,
                'is_available' => true,
            ],
        );

        Product::query()->updateOrCreate(
            ['sku' => 'PSN-20-EUR'],
            [
                'title' => 'PlayStation Store 20 EUR',
                'category_id' => $bySlug['playstation'],
                'sort_rank' => 9_000,
                'price_cents' => 2000,
                'stock_qty' => 25,
                'is_available' => true,
            ],
        );

        if (Product::query()->count() >= 5000) {
            return;
        }

        $slugs = array_column($categories, 'slug');
        $now = now();
        $batch = [];

        for ($i = 1; $i <= 5000; $i++) {
            $slug = $slugs[$i % count($slugs)];
            $stock = $i % 17 === 0 ? 0 : (1 + ($i % 40));

            $batch[] = [
                'sku' => sprintf('SKU-%05d', $i),
                'title' => sprintf('Digital item #%d', $i),
                'category_id' => $bySlug[$slug],
                'sort_rank' => 5000 - ($i % 5000),
                'price_cents' => 199 + ($i % 50) * 100,
                'stock_qty' => $stock,
                'is_available' => $stock > 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) === 500) {
                DB::table('products')->insert($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            DB::table('products')->insert($batch);
        }
    }
}
