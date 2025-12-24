<?php

namespace Modules\Delivery\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Services\SidebarGroup;
use Modules\Core\Services\SidebarItem;
use Modules\Core\Services\SidebarService;

class SidebarServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void {
        $this->app->singleton(SidebarService::class, function () {
            return new SidebarService();
        });
    }

    public function boot(): void {
        $sidebar = $this->app->make(SidebarService::class);

        $sidebar->add(
            (new SidebarGroup('Delivery', 'solar-users-group-rounded-bold-duotone' , ['Delivery View'], 2 ))
                ->add(new SidebarItem('Delivery List' , 'solar-user-bold-duotone', ['Delivery View'] , 'delivery-pricing.index', 2))
                ->add(new SidebarItem('Delivery Zones' , 'solar-user-bold-duotone', ['Delivery View'] , 'delivery-zone.index', 2))
        );
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [];
    }
}
