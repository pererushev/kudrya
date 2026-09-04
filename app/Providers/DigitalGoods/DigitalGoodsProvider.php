<?php

namespace App\Providers\DigitalGoods;

use App\Enums\ProviderName;

interface DigitalGoodsProvider
{
    public function name(): ProviderName;

    public function fulfill(string $idempotencyKey, string $sku): ProviderResult;

    public function fetchStatus(string $idempotencyKey): ProviderResult;
}
