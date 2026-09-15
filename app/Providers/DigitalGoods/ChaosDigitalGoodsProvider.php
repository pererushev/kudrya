<?php

namespace App\Providers\DigitalGoods;

use App\Domain\KeyPool;
use App\Enums\ProviderName;
use App\Models\ProviderIssuance;

class ChaosDigitalGoodsProvider implements DigitalGoodsProvider
{
    /**
     * Forced fulfill outcomes for tests: issued|failed|timeout|out_of_stock.
     *
     * @var list<string>
     */
    private static array $forcedOutcomes = [];

    public function __construct(
        private readonly ProviderName $name,
        private readonly float $failRate,
        private readonly float $timeoutRate,
        private readonly bool $chaosEnabled,
        private readonly KeyPool $keys,
    ) {}

    public static function forceNext(?string $outcome): void
    {
        self::$forcedOutcomes = $outcome === null ? [] : [$outcome];
    }

    /**
     * @param  list<string>  $outcomes
     */
    public static function forceSequence(array $outcomes): void
    {
        self::$forcedOutcomes = array_values($outcomes);
    }

    public function name(): ProviderName
    {
        return $this->name;
    }

    public function fulfill(string $requestId, string $sku, string $orderId): ProviderResult
    {
        $existing = ProviderIssuance::query()
            ->where('idempotency_key', $requestId)
            ->first();

        if ($existing !== null) {
            return $this->fromIssuance($existing);
        }

        $outcome = $this->decideOutcome();

        if ($outcome === 'failed' || $outcome === 'out_of_stock') {
            $reason = $outcome === 'out_of_stock' ? 'out_of_stock' : 'provider_rejected';

            return ProviderResult::failed($reason);
        }

        $code = $this->keys->allocate($orderId, $requestId, $this->name);

        if ($code === null) {
            return ProviderResult::failed('out_of_stock');
        }

        $now = now();
        $inserted = ProviderIssuance::query()->insertOrIgnore([
            [
                'provider' => $this->name->value,
                'idempotency_key' => $requestId,
                'status' => 'issued',
                'code' => $code,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $stored = ProviderIssuance::query()
            ->where('idempotency_key', $requestId)
            ->firstOrFail();

        if ($inserted === 0) {
            return $this->fromIssuance($stored);
        }

        if ($outcome === 'timeout') {
            throw new ProviderTimeoutException($this->name, $requestId);
        }

        return $this->fromIssuance($stored);
    }

    public function fetchStatus(string $requestId): ProviderResult
    {
        $existing = ProviderIssuance::query()
            ->where('idempotency_key', $requestId)
            ->first();

        if ($existing === null) {
            return ProviderResult::notFound();
        }

        return $this->fromIssuance($existing);
    }

    private function decideOutcome(): string
    {
        if (self::$forcedOutcomes !== []) {
            return array_shift(self::$forcedOutcomes);
        }

        if (! $this->chaosEnabled) {
            return 'issued';
        }

        $roll = $this->unitRandom();

        if ($roll < $this->failRate) {
            return 'failed';
        }

        if ($roll < $this->failRate + $this->timeoutRate) {
            return 'timeout';
        }

        return 'issued';
    }

    private function unitRandom(): float
    {
        return random_int(0, 10_000) / 10_000;
    }

    private function fromIssuance(ProviderIssuance $issuance): ProviderResult
    {
        if ($issuance->status === 'issued' && $issuance->code !== null) {
            return ProviderResult::issued($issuance->code);
        }

        $reason = $issuance->status === 'out_of_stock' ? 'out_of_stock' : 'provider_rejected';

        return ProviderResult::failed($reason);
    }
}
