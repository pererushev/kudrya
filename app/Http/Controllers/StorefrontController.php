<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StorefrontController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $slug = $request->query('category', 'steam');

        $category = Category::query()->where('slug', $slug)->first();

        if ($category === null) {
            return response()->json([
                'message' => 'Unknown category.',
            ], 404);
        }

        $items = Product::query()
            ->where('category_id', $category->id)
            ->where('is_available', true)
            ->orderByDesc('sort_rank')
            ->orderBy('id')
            ->limit((int) $request->integer('limit', 50))
            ->get(['sku', 'title', 'price_cents', 'stock_qty', 'sort_rank']);

        return response()->json([
            'category' => $category->slug,
            'items' => $items,
        ]);
    }
}
