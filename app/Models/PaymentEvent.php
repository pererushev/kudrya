<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentEvent extends Model
{
    protected $fillable = [
        'event_id',
        'order_id',
        'amount_cents',
        'status',
        'payload',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'amount_cents' => 'integer',
            'received_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
