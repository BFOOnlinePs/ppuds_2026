<?php

namespace Modules\Coupon\Providers;

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
            (new SidebarGroup('Coupons', 'solar-box-bold-duotone', ['Coupon View']))
                ->add(new SidebarItem('Coupons List' , 'solar-box-bold-duotone', ['Coupon View'] , 'coupons.index'))
                ->add(new SidebarItem('Add Coupon' , 'solar-box-bold-duotone', ['Coupon Create'] , 'coupons.add'))
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
