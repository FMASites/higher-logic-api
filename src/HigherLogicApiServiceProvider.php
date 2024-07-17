<?php

namespace FMASites\HigherLogicApi;

use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;

/**
 * Service Provider for Higher Logic services
 */
class HigherLogicApiServiceProvider extends ServiceProvider
{
    public function register()
    {
        // The config will live under the fmasites config key, which serves as an organization-wide namespace
        // to prevent name collisions with other third-party packages.
        $this->mergeConfigFrom(__DIR__ . '/../config/higherlogic.php', 'fmasites');

        // One instance should do it
        $this->app->singleton(RealMagnet::class, function ($app) {
            $userName = config('fmasites.higherlogic.realmagnet.username');
            $password = config('fmasites.higherlogic.realmagnet.password');
            $client = new Client([
                'base_uri' => 'https://dna.magnetmail.net/ApiAdapter/Rest/',
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
            ]);

            return new RealMagnet($client, $userName, $password);
        });
    }

    public function boot()
    {

    }
}
