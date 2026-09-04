<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku',
        'title',
        'category_id',
        'sort_rank',
        'price_cents',
        'stock_qty',
        'is_available',
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'stock_qty' => 'integer',
            'sort_rank' => 'integer',
            'is_available' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Product $product): void {
            $product->is_available = $product->stock_qty > 0;
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
