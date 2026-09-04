<?php

namespace App\Console\Commands;

use App\Models\Category;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CatalogExplainCommand extends Command
{
    protected $signature = 'catalog:explain {category=steam}';

    protected $description = 'Print EXPLAIN (ANALYZE, BUFFERS) for the hot storefront query';

    public function handle(): int
    {
        $slug = (string) $this->argument('category');
        $category = Category::query()->where('slug', $slug)->first();

        if ($category === null) {
            $this->error('Unknown category '.$slug);

            return self::FAILURE;
        }

        $sql = '
            EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT)
            SELECT sku, title, price_cents, stock_qty, sort_rank, id
            FROM products
            WHERE category_id = ? AND is_available = true
            ORDER BY sort_rank DESC, id
            LIMIT 50
        ';

        $rows = DB::select($sql, [$category->id]);

        foreach ($rows as $row) {
            $plan = (array) $row;
            $this->line((string) ($plan['QUERY PLAN'] ?? array_values($plan)[0] ?? ''));
        }

        return self::SUCCESS;
    }
}
