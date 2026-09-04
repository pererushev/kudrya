<?php

namespace App\Models;

use App\Enums\FulfillmentStatus;
use App\Enums\ProviderName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Fulfillment extends Model
{
    protected $fillable = [
        'order_id',
        'provider',
        'idempotency_key',
        'status',
        'code',
        'provider_ref',
        'last_error',
        'attempt',
    ];

    protected function casts(): array
    {
        return [
            'status' => FulfillmentStatus::class,
            'provider' => ProviderName::class,
            'attempt' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
