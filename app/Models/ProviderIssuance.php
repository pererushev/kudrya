<?php

namespace App\Models;

use App\Enums\ProviderName;
use Illuminate\Database\Eloquent\Model;

class ProviderIssuance extends Model
{
    protected $fillable = [
        'provider',
        'idempotency_key',
        'status',
        'code',
    ];

    protected function casts(): array
    {
        return [
            'provider' => ProviderName::class,
        ];
    }
}
