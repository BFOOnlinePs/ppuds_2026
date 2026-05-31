<?php

namespace Modules\Content\Providers;

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
            (new SidebarGroup('Content Management', 'solar-document-text-bold-duotone' , ['Content View List'] ))
            ->add(new SidebarItem('Banners List' , 'solar-gallery-wide-bold-duotone' , ['Banner View'] ,  'banners.index' ))
            ->add(new SidebarItem('Pages List' , 'solar-documents-bold-duotone' , ['Page View'] ,  'pages.index' ))
            ->add(new SidebarItem('Faqs List' , 'solar-question-circle-bold-duotone' , ['Faq View'] ,  'faqs.index' ))
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
