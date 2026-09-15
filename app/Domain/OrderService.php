<?php

namespace App\Domain;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function create(string $sku): Order
    {
        $product = Product::query()->where('sku', $sku)->first();

        if ($product === null) {
            throw (new ModelNotFoundException)->setModel(Product::class, [$sku]);
        }

        if (! $product->is_available) {
            throw ValidationException::withMessages([
                'sku' => 'SKU is out of stock.',
            ]);
        }

        return Order::query()->create([
            'product_id' => $product->id,
            'sku' => $product->sku,
            'amount_cents' => $product->price_cents,
            'status' => OrderStatus::Created,
        ]);
    }
}
