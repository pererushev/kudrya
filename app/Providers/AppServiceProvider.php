<?php

namespace App\Providers;

use App\Enums\ProviderName;
use App\Providers\DigitalGoods\ChaosDigitalGoodsProvider;
use App\Providers\DigitalGoods\ProviderRegistry;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProviderRegistry::class, function (): ProviderRegistry {
            $chaos = (bool) config('commerce.providers.chaos');

            return new ProviderRegistry([
                ProviderName::A->value => new ChaosDigitalGoodsProvider(
                    ProviderName::A,
                    (float) config('commerce.providers.a.fail_rate'),
                    (float) config('commerce.providers.a.timeout_rate'),
                    $chaos,
                ),
                ProviderName::B->value => new ChaosDigitalGoodsProvider(
                    ProviderName::B,
                    (float) config('commerce.providers.b.fail_rate'),
                    (float) config('commerce.providers.b.timeout_rate'),
                    $chaos,
                ),
            ]);
        });
    }

    public function boot(): void
    {
        JsonResource::withoutWrapping();
    }
}
