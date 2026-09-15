<?php

namespace App\Enums;

enum ProviderName: string
{
    case A = 'a';
    case B = 'b';

    public function fallback(): ?self
    {
        return $this === self::A ? self::B : null;
    }

    public function requestId(string $orderId): string
    {
        return 'req_'.$orderId.'-'.$this->value;
    }

    public function idempotencyKey(string $orderId): string
    {
        return $this->requestId($orderId);
    }
}
