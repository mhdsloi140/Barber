<?php

namespace App\Providers;

use App\Services\ultraMessage\OTPService;
use App\Services\ultraMessage\UltraMsgService; 
use Illuminate\Support\ServiceProvider;

class WhatsAppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(UltraMsgService::class, function ($app) {
            return new UltraMsgService();
        });

        $this->app->singleton(OTPService::class, function ($app) {
            return new OTPService($app->make(UltraMsgService::class));
        });
    }

    public function boot(): void
    {
        //
    }
}
