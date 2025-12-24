<?php

namespace Modules\Items\Providers;

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
            (new SidebarItem('Orders List' , 'solar-box-bold-duotone', ['Order View'] , 'orders.index', 1))
        );

        $sidebar->add(
            (new SidebarGroup('Products', 'solar-box-bold-duotone', ['Product View']))
                ->add(new SidebarItem('Products List' , 'solar-box-bold-duotone', ['Product View'] , 'products.index'))
                ->add(new SidebarItem('Add Product' , 'solar-box-bold-duotone', ['Product Create'] , 'products.add'))
                ->add(new SidebarItem('Categories List' , 'solar-box-bold-duotone', ['Category View'] , 'categories.index'))
                ->add(new SidebarItem('Attributes List' , 'solar-box-bold-duotone', ['Attribute View'] , 'attributes.index'))
                ->add(new SidebarItem('Tags List' , 'solar-box-bold-duotone', ['Tag View'] , 'tags.index'))
                ->add(new SidebarItem('Labels List' , 'solar-box-bold-duotone', ['Label View'] , 'labels.index'))
                ->add(new SidebarItem('Brands List' , 'solar-box-bold-duotone', ['Brand View'] , 'brands.index'))
                ->add(new SidebarItem('Offers List' , 'solar-box-bold-duotone', ['Offer View'] , 'offers.index'))
                ->add(new SidebarItem('Addons List' , 'solar-box-bold-duotone', ['Addon View'] , 'addons.index'))
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
