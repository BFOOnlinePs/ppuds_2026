<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Registration any application services.
     */
    public function register(): void
    {
        $this->app->register(\L5Swagger\L5SwaggerServiceProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Replace wirechat's "new chat/group" modals with versions that enforce
        // the application's chat contact rules (ChatContactService).
        Livewire::component('wirechat.new.chat', \App\Livewire\Wirechat\NewChat::class);
        Livewire::component('wirechat.new.group', \App\Livewire\Wirechat\NewGroup::class);
    }
}
