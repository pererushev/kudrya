<?php

namespace App\Http\Resources;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $delivered = $this->status === OrderStatus::Delivered;

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'status' => $this->status->value,
            'amount' => (int) round($this->amount_cents / 100),
            'amount_cents' => $this->amount_cents,
            'currency' => 'RUB',
            'code' => $delivered ? $this->decryptedCode() : null,
            'provider' => $delivered ? $this->fulfillment?->provider?->value : null,
            'failure_reason' => $this->failure_reason,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
