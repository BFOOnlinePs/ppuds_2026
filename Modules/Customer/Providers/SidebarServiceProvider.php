<?php

namespace Modules\Customer\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Services\SidebarGroup;
use Modules\Core\Services\SidebarItem;
use Modules\Core\Services\SidebarService;

class SidebarServiceProvider extends ServiceProvider
{
    /**
     * Registration the service provider.
     */
    public function register(): void {
        $this->app->singleton(SidebarService::class, function () {
            return new SidebarService();
        });
    }

    public function boot(): void {
        $sidebar = $this->app->make(SidebarService::class);

        $sidebar->add(
            (new SidebarGroup('Customers', 'solar-users-group-rounded-bold-duotone', ['Customer View'] ,2))
                ->add(new SidebarItem('Customers List' , 'solar-users-group-two-rounded-bold-duotone', ['Customer View'] , 'customers.index'))
                ->add(new SidebarItem('Add Customer' , 'solar-user-plus-rounded-bold-duotone', ['Customer Create'] , 'customers.add'))
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
