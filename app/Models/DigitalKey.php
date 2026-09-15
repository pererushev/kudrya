<?php

namespace App\Models;

use App\Enums\ProviderName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DigitalKey extends Model
{
    protected $fillable = [
        'code',
        'order_id',
        'request_id',
        'provider',
        'allocated_at',
    ];

    protected function casts(): array
    {
        return [
            'provider' => ProviderName::class,
            'allocated_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
