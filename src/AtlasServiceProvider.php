<?php

namespace Oremis\Atlas;

use Illuminate\Support\ServiceProvider;
use Oremis\Atlas\Services\AtlasService;
use Oremis\Atlas\Services\PioApiService;
use Oremis\Atlas\Services\SyncUserService;

class AtlasServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/atlas.php', 'atlas');

        $this->mergeSocialiteGoogleConfig();

        $this->app->singleton(AtlasService::class, function () {
            return new AtlasService(
                app(PioApiService::class),
                app(SyncUserService::class),
            );
        });

        $this->app->singleton('atlas', AtlasService::class);
    }

    protected function mergeSocialiteGoogleConfig()
    {
        $googleConfig = [
            'client_id' => env('GOOGLE_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CLIENT_SECRET'),
            'redirect' => env('GOOGLE_REDIRECT_URL'),
        ];

        // Only override if app has NOT defined them
        config([
            'services.google' =>
            array_merge($googleConfig, config('services.google', []))
        ]);
    }


    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/atlas.php' => config_path('atlas.php'),
        ], 'atlas-config');
    }
}
