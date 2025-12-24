<?php

namespace Modules\Core\Providers;

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
            (new SidebarItem('Home', 'solar-home-2-bold-duotone', [] ,  'home' , 1))
        );

        $sidebar->add(
            (new SidebarGroup('Users', 'solar-users-group-rounded-bold-duotone' , ['User View'] )) // قيمة sort 3
            ->add(new SidebarItem('Users List' , 'solar-users-group-rounded-bold-duotone' , ['User View'] ,  'users.index' ))
                ->add(new SidebarItem('Add User' , 'solar-users-group-rounded-bold-duotone' , ['User Create'] , 'users.add' ))
        );

        $sidebar->add(
            (new SidebarGroup('Settings', 'solar-settings-bold-duotone' , ['Setting View'] ))
            ->add(new SidebarItem('Settings', 'solar-settings-bold-duotone', ['Setting View'] , 'settings' ))
            ->add(new SidebarItem('Roles And Permissions', 'solar-lock-bold-duotone', ['Roles And Permissions View'] , 'roles.index' ))
            ->add(new SidebarItem('Currency', 'solar-wallet-money-bold-duotone', ['Currency View'] ,  'currencies.index' ))
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
