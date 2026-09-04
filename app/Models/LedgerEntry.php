<?php

namespace App\Models;

use App\Enums\LedgerAccount;
use App\Enums\LedgerReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerEntry extends Model
{
    protected $fillable = [
        'order_id',
        'account',
        'amount_cents',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'account' => LedgerAccount::class,
            'reason' => LedgerReason::class,
            'amount_cents' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
