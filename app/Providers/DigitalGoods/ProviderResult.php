<?php

namespace App\Providers\DigitalGoods;

final readonly class ProviderResult
{
    public function __construct(
        public string $outcome,
        public ?string $code = null,
        public ?string $error = null,
    ) {}

    public static function issued(string $code): self
    {
        return new self('issued', $code);
    }

    public static function failed(string $error): self
    {
        return new self('failed', error: $error);
    }

    public static function notFound(): self
    {
        return new self('not_found');
    }

    public function isIssued(): bool
    {
        return $this->outcome === 'issued' && $this->code !== null;
    }

    public function isFailed(): bool
    {
        return $this->outcome === 'failed';
    }

    public function isNotFound(): bool
    {
        return $this->outcome === 'not_found';
    }
}
