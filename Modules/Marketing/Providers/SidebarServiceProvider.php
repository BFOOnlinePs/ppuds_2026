<?php

namespace Modules\Marketing\Providers;

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
            (new SidebarGroup('Marketing', 'solar-graph-up-bold-duotone' , ['Marketing View'] ))
            ->add(new SidebarItem('Loyalty Rules List' , 'solar-checklist-bold-duotone' , ['Loyalty Rules View'] ,  'loyalty-rules.index' ))
            ->add(new SidebarItem('Loyalty Tiers List' , 'solar-medal-star-square-bold-duotone' , ['Loyalty Tiers View'] ,  'loyalty-tiers.index' ))
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
