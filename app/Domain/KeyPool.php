<?php

namespace App\Domain;

use App\Enums\ProviderName;
use App\Models\DigitalKey;
use Illuminate\Support\Facades\DB;

class KeyPool
{
    public function allocate(string $orderId, string $requestId, ProviderName $provider): ?string
    {
        return DB::transaction(function () use ($orderId, $requestId, $provider) {
            $existing = DigitalKey::query()
                ->where('request_id', $requestId)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing->code;
            }

            $byOrder = DigitalKey::query()
                ->where('order_id', $orderId)
                ->lockForUpdate()
                ->first();

            if ($byOrder !== null) {
                return $byOrder->code;
            }

            /** @var DigitalKey|null $free */
            $free = DigitalKey::query()
                ->whereNull('allocated_at')
                ->orderBy('id')
                ->lock('for update skip locked')
                ->first();

            if ($free === null) {
                return null;
            }

            $free->order_id = $orderId;
            $free->request_id = $requestId;
            $free->provider = $provider;
            $free->allocated_at = now();
            $free->save();

            return $free->code;
        });
    }
}
