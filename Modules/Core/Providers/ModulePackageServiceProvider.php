<?php

namespace Modules\Core\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Services\ModulePackageService;

class ModulePackageServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        // تسجيل الـ Service
        $this->app->singleton(ModulePackageService::class);
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [ModulePackageService::class];
    }
}
