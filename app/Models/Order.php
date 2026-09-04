<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Crypt;

class Order extends Model
{
    use HasUuids;

    protected $fillable = [
        'product_id',
        'sku',
        'amount_cents',
        'status',
        'version',
        'paid_at',
        'delivered_at',
        'failed_at',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'amount_cents' => 'integer',
            'version' => 'integer',
            'paid_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function paymentEvents(): HasMany
    {
        return $this->hasMany(PaymentEvent::class);
    }

    public function fulfillment(): HasOne
    {
        return $this->hasOne(Fulfillment::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function decryptedCode(): ?string
    {
        $encrypted = $this->fulfillment?->code;

        if ($encrypted === null) {
            return null;
        }

        return Crypt::decryptString($encrypted);
    }
}
