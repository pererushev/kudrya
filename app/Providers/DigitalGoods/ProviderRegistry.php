<?php

namespace App\Providers\DigitalGoods;

use App\Enums\ProviderName;
use InvalidArgumentException;

class ProviderRegistry
{
    /**
     * @param  array<string, DigitalGoodsProvider>  $providers
     */
    public function __construct(
        private readonly array $providers,
    ) {}

    public function get(ProviderName $name): DigitalGoodsProvider
    {
        return $this->providers[$name->value] ?? throw new InvalidArgumentException('Unknown provider '.$name->value);
    }
}
