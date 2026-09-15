<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\DigitalKey;
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
            ['slug' => 'subscriptions', 'name' => 'Subscriptions'],
            ['slug' => 'giftcards', 'name' => 'Gift cards'],
            ['slug' => 'nintendo', 'name' => 'Nintendo'],
            ['slug' => 'mobile', 'name' => 'Mobile'],
        ];

        foreach ($categories as $row) {
            Category::query()->updateOrCreate(['slug' => $row['slug']], $row);
        }

        $bySlug = Category::query()->pluck('id', 'slug');
        $keyCount = count(require database_path('data/digital_keys.php'));

        $featured = [
            ['sku' => 'STEAM-TOPUP-500', 'title' => 'Пополнение Steam 500 ₽', 'category' => 'steam', 'rank' => 12_000, 'price' => 500],
            ['sku' => 'STEAM-TOPUP-1000', 'title' => 'Пополнение Steam 1000 ₽', 'category' => 'steam', 'rank' => 11_500, 'price' => 1000],
            ['sku' => 'STEAM-TOPUP-2500', 'title' => 'Пополнение Steam 2500 ₽', 'category' => 'steam', 'rank' => 11_000, 'price' => 2500],
            ['sku' => 'KEY-CS2-PRIME', 'title' => 'CS2 Prime Status ключ', 'category' => 'steam', 'rank' => 10_500, 'price' => 1290],
            ['sku' => 'KEY-GTA5', 'title' => 'GTA V ключ активации', 'category' => 'steam', 'rank' => 10_000, 'price' => 1990],
            ['sku' => 'KEY-EFT', 'title' => 'Escape from Tarkov ключ', 'category' => 'steam', 'rank' => 9_500, 'price' => 3490],
            ['sku' => 'SUB-DISCORD-1M', 'title' => 'Discord Nitro 1 месяц', 'category' => 'subscriptions', 'rank' => 9_000, 'price' => 399],
            ['sku' => 'SUB-YT-3M', 'title' => 'YouTube Premium 3 месяца', 'category' => 'subscriptions', 'rank' => 8_500, 'price' => 1490],
            ['sku' => 'SUB-SPOTIFY-1M', 'title' => 'Spotify Premium 1 месяц', 'category' => 'subscriptions', 'rank' => 8_000, 'price' => 299],
            ['sku' => 'GIFT-PSN-1000', 'title' => 'PlayStation Store карта 1000 ₽', 'category' => 'playstation', 'rank' => 7_500, 'price' => 1000],
            ['sku' => 'GIFT-XBOX-1500', 'title' => 'Xbox Gift Card 1500 ₽', 'category' => 'xbox', 'rank' => 7_000, 'price' => 1500],
            ['sku' => 'GIFT-ROBLOX-800', 'title' => 'Roblox 800 Robux', 'category' => 'giftcards', 'rank' => 6_500, 'price' => 890],
        ];

        foreach ($featured as $row) {
            Product::query()->updateOrCreate(
                ['sku' => $row['sku']],
                [
                    'title' => $row['title'],
                    'category_id' => $bySlug[$row['category']],
                    'sort_rank' => $row['rank'],
                    'price_cents' => $row['price'] * 100,
                    'stock_qty' => $keyCount,
                    'is_available' => true,
                ],
            );
        }

        foreach (require database_path('data/digital_keys.php') as $code) {
            DigitalKey::query()->updateOrCreate(['code' => $code], ['code' => $code]);
        }

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
