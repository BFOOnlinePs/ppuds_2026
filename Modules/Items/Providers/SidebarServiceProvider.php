<?php

namespace Modules\Items\Providers;

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
            (new SidebarItem('Orders List' , 'solar-cart-check-bold-duotone', ['Order View'] , 'orders.index', 1))
        );

        $sidebar->add(
            (new SidebarGroup('Products', 'solar-bag-4-bold-duotone', ['Product View']))
                ->add(new SidebarItem('Products List' , 'solar-box-minimalistic-bold-duotone', ['Product View'] , 'products.index'))
                ->add(new SidebarItem('Add Product' , 'solar-cart-plus-bold-duotone', ['Product Create'] , 'products.add'))
                ->add(new SidebarItem('Categories List' , 'solar-widget-4-bold-duotone', ['Category View'] , 'categories.index'))
                ->add(new SidebarItem('Attributes List' , 'solar-tuning-2-bold-duotone', ['Attribute View'] , 'attributes.index'))
                ->add(new SidebarItem('Tags List' , 'solar-tag-bold-duotone', ['Tag View'] , 'tags.index'))
                ->add(new SidebarItem('Labels List' , 'solar-tag-horizontal-bold-duotone', ['Label View'] , 'labels.index'))
                ->add(new SidebarItem('Brands List' , 'solar-shop-bold-duotone', ['Brand View'] , 'brands.index'))
                ->add(new SidebarItem('Offers List' , 'solar-sale-square-bold-duotone', ['Offer View'] , 'offers.index'))
                ->add(new SidebarItem('Addons List' , 'solar-widget-add-bold-duotone', ['Addon View'] , 'addons.index'))
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
