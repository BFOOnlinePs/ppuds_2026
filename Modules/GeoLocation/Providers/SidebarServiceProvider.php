<?php

namespace Modules\GeoLocation\Providers;

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
            (new SidebarGroup('GeoLocation', 'solar-global-bold-duotone', ['GeoLocation View']))
                ->add(new SidebarItem('Countries List' , 'solar-box-bold-duotone', ['Country View'] , 'countries.index'))
                ->add(new SidebarItem('Governorates List' , 'solar-box-bold-duotone', ['Governorate View'] , 'governorates.index'))
                ->add(new SidebarItem('Cities List' , 'solar-box-bold-duotone', ['City View'] , 'cities.index'))
                ->add(new SidebarItem('Districts List' , 'solar-box-bold-duotone', ['District View'] , 'districts.index'))
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
