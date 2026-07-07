<?php

namespace Modules\Core\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Services\SidebarGroup;
use Modules\Core\Services\SidebarItem;
use Modules\Core\Services\SidebarService;

class SidebarServiceProvider extends ServiceProvider
{
    /**
     * Registration the service provider.
     */
    public function register(): void
    {
        $this->app->singleton(SidebarService::class, function () {
            return new SidebarService();
        });
    }

    public function boot(): void
    {
        $sidebar = $this->app->make(SidebarService::class);

        $sidebar->add(
            (new SidebarItem('Home', 'solar-home-2-bold-duotone', [],  'home', 10))
        );

        $sidebar->add(
            (new SidebarGroup('Users', 'solar-users-group-rounded-bold-duotone', [], 30))
                ->add(new SidebarItem('Users List', 'solar-users-group-two-rounded-bold-duotone', ['User View List'],  'users.index'))
                ->add(new SidebarItem('Add User', 'solar-user-plus-rounded-bold-duotone', ['User Create'], 'users.add'))
        );

        $sidebar->add(
            (new SidebarGroup('Settings', 'solar-settings-bold-duotone', [], 140))
                ->add(new SidebarItem('System Settings', 'solar-settings-bold-duotone', ['Setting View'], 'settings'))
                ->add(new SidebarItem('Sync System Data', 'solar-server-square-update-bold-duotone', ['Sync System Data View'], 'sync-system-data.index'))
                ->add(new SidebarItem('Sync Companies With University API', 'solar-buildings-bold-duotone', ['Sync System Data View'], 'sync-company-supervisors.index'))
                ->add(new SidebarItem('Roles And Permissions', 'solar-shield-keyhole-bold-duotone', ['Roles And Permissions View'], 'roles.index'))
                ->add(new SidebarItem('Currencies', 'solar-wallet-money-bold-duotone', ['Currency View List'],  'currencies.index'))
                ->add(new SidebarItem('Countries', 'solar-global-bold-duotone', ['Country View List'], 'countries.index'))
                ->add(new SidebarItem('Governorates', 'solar-map-bold-duotone', ['Governorate View List'], 'governorates.index'))
                ->add(new SidebarItem('Cities', 'solar-city-bold-duotone', ['City View List'], 'cities.index'))
                ->add(new SidebarItem('Districts', 'solar-map-point-bold-duotone', ['District View List'], 'districts.index'))
                ->add(new SidebarItem('Specialties', 'solar-square-academic-cap-bold-duotone', ['Student View List'], 'majors.index'))
                ->add(new SidebarItem('Courses', 'solar-book-bookmark-bold-duotone', ['Course View List'], 'courses.index'))
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
