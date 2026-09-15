<?php

namespace App\Providers\DigitalGoods;

use App\Enums\ProviderName;

interface DigitalGoodsProvider
{
    public function name(): ProviderName;

    public function fulfill(string $requestId, string $sku, string $orderId): ProviderResult;

    public function fetchStatus(string $requestId): ProviderResult;
}
