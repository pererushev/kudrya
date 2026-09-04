<?php

namespace App\Providers\DigitalGoods;

use App\Enums\ProviderName;
use App\Models\ProviderIssuance;
use Illuminate\Support\Str;

class ChaosDigitalGoodsProvider implements DigitalGoodsProvider
{
    /**
     * Forced fulfill outcomes for tests: issued|failed|timeout.
     *
     * @var list<string>
     */
    private static array $forcedOutcomes = [];

    public function __construct(
        private readonly ProviderName $name,
        private readonly float $failRate,
        private readonly float $timeoutRate,
        private readonly bool $chaosEnabled,
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

    public function fulfill(string $idempotencyKey, string $sku): ProviderResult
    {
        $existing = ProviderIssuance::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing !== null) {
            return $this->fromIssuance($existing);
        }

        $outcome = $this->decideOutcome();
        $code = $outcome === 'failed'
            ? null
            : sprintf('%s-%s-%s', strtoupper($this->name->value), strtoupper($sku), Str::upper(Str::random(12)));
        $status = $outcome === 'failed' ? 'failed' : 'issued';

        $now = now();
        $inserted = ProviderIssuance::query()->insertOrIgnore([
            [
                'provider' => $this->name->value,
                'idempotency_key' => $idempotencyKey,
                'status' => $status,
                'code' => $code,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $stored = ProviderIssuance::query()
            ->where('idempotency_key', $idempotencyKey)
            ->firstOrFail();

        if ($inserted === 0) {
            return $this->fromIssuance($stored);
        }

        if ($outcome === 'timeout') {
            throw new ProviderTimeoutException($this->name, $idempotencyKey);
        }

        return $this->fromIssuance($stored);
    }

    public function fetchStatus(string $idempotencyKey): ProviderResult
    {
        $existing = ProviderIssuance::query()
            ->where('idempotency_key', $idempotencyKey)
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

        return ProviderResult::failed('provider_rejected');
    }
}
