<?php

namespace Modules\Clinic\Providers;

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

//        $sidebar->add(
//            (new SidebarGroup('Diseases', 'solar-virus-bold-duotone'))
//                ->add(new SidebarItem('Diseases List' , 'solar-virus-bold-duotone' , 'diseases.index'))
//        );

        $sidebar->add(
            new SidebarItem('Appointments List' , 'solar-calendar-date-bold-duotone', ['Appointment View'] , 'appointments.index' , 2)
        );

        $sidebar->add(
            (new SidebarGroup('Foods', 'solar-chef-hat-bold-duotone', ['Food View'] , 4))
                ->add(new SidebarItem('Food Categories List' , 'solar-widget-4-bold-duotone', ['Food Category View'] , 'food.categories.index'))
                ->add(new SidebarItem('Food Items List' , 'solar-plate-bold-duotone', ['Food Item View'] , 'food.items.index'))
        );

        $sidebar->add(
            (new SidebarGroup('Programs', 'solar-clipboard-bold-duotone', ['Program View'] , 5))
                ->add(new SidebarItem('Categories List' , 'solar-widget-4-bold-duotone', ['Category View'] , 'program.categories.index'))
                ->add(new SidebarItem('Instructions List' , 'solar-document-text-bold-duotone', ['Program Instruction View'] , 'program.instructions.index'))
                ->add(new SidebarItem('Type of Meal List' , 'solar-chef-hat-minimalistic-bold-duotone', ['Program Type Of Meal View'] , 'program.types-of-meals.index'))
                ->add(new SidebarItem('Programs List' , 'solar-clipboard-list-bold-duotone', ['Program View'] , 'program.programs.index'))
                ->add(new SidebarItem('Customer Programs List' , 'solar-user-heart-bold-duotone', ['Customer Program View'] , 'program.customer-programs.index'))
        );

        $sidebar->add(
            (new SidebarGroup('اعدادات العيادة', 'solar-hospital-bold-duotone', ['Clinic Setting View'] , 7))
                ->add(new SidebarItem('Rooms List' , 'solar-bed-bold-duotone', ['Room View'] , 'rooms.index'))
                ->add(new SidebarItem('Surveys List' , 'solar-checklist-bold-duotone', ['Survey View'] , 'surveys.index'))
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
