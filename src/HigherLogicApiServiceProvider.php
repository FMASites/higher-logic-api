<?php

namespace HigherLogicApi;

use Illuminate\Support\ServiceProvider;

class HigherLogicApiServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/higherlogic.php', 'higherlogic');

        $this->app->singleton(HigherLogicApi::class, function ($app) {
            $userName = config('higherlogic.username');
            $password = config('higherlogic.password');

            return new HigherLogicApi($userName, $password);
        });
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/higherlogic.php' => config_path('higherlogic.php'),
            ], 'config');
        }
    }
}
