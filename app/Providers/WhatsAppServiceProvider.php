<?php

namespace App\Providers;

use App\Services\OTPService;
use App\Services\UltraMsgService;
use Illuminate\Support\ServiceProvider;

class WhatsAppServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // تسجيل UltraMsgService كـ Singleton
        $this->app->singleton(UltraMsgService::class, function ($app) {
            return new UltraMsgService();
        });

        // تسجيل OTPService مع حقن UltraMsgService
        $this->app->singleton(OTPService::class, function ($app) {
            return new OTPService($app->make(UltraMsgService::class));
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
