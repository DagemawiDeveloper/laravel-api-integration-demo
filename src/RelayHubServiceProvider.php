<?php

namespace Dagemawi\RelayHub;

use Dagemawi\RelayHub\Contracts\SignatureVerifier;
use Dagemawi\RelayHub\Services\HmacSignature;
use Illuminate\Support\ServiceProvider;

class RelayHubServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/relayhub.php', 'relayhub');

        $this->app->singleton(SignatureVerifier::class, HmacSignature::class);
        $this->app->singleton(HmacSignature::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->app['router']->prefix((string) config('relayhub.route_prefix', 'api/relayhub'))
            ->middleware('api')
            ->group(__DIR__ . '/../routes/api.php');

        $this->publishes([
            __DIR__ . '/../config/relayhub.php' => config_path('relayhub.php'),
        ], 'relayhub-config');
    }
}
