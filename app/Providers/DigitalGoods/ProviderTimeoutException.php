<?php

namespace App\Providers\DigitalGoods;

use App\Enums\ProviderName;

final class ProviderTimeoutException extends \RuntimeException
{
    public function __construct(
        public readonly ProviderName $provider,
        public readonly string $idempotencyKey,
    ) {
        parent::__construct(sprintf(
            'Provider %s timed out for key %s (issuance may already exist)',
            $provider->value,
            $idempotencyKey,
        ));
    }
}
