<?php

namespace Modules\PPUDS\Providers;

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
            (new SidebarGroup('Students', 'solar-users-group-rounded-bold-duotone' , ['Student View']))
//            ->add(new SidebarItem('Add Student' , 'solar-users-group-rounded-bold-duotone' , ['Student View'] ,  'students.add' ))
            ->add(new SidebarItem('Students List' , 'solar-users-group-rounded-bold-duotone' , ['Student Create'] ,  'students.index' ))
        );

        $sidebar->add(
            (new SidebarGroup('Specialties', 'solar-users-group-rounded-bold-duotone' , ['Student View']))
                ->add(new SidebarItem('Specialties List' , 'solar-users-group-rounded-bold-duotone' , ['Student View'] ,  'majors.index' ))
        );

        $sidebar->add(
            (new SidebarGroup('Registration', 'solar-users-group-rounded-bold-duotone' , ['Student View']))
                ->add(new SidebarItem('Add Registration' , 'solar-users-group-rounded-bold-duotone' , ['Student View'] ,  'students.index' ))
                ->add(new SidebarItem('Registration List' , 'solar-users-group-rounded-bold-duotone' , ['Student Create'] ,  'students.index' ))
        );

        $sidebar->add(
            (new SidebarGroup('Companies', 'solar-users-group-rounded-bold-duotone' , ['Company View']))
                ->add(new SidebarItem('Companies Category List' , 'solar-users-group-rounded-bold-duotone' , ['Company Category View'] ,  'company-category.index' ))
                ->add(new SidebarItem('Companies Department List' , 'solar-users-group-rounded-bold-duotone' , ['Company Department View'] ,  'company-department.index' ))
                ->add(new SidebarItem('Add Company' , 'solar-users-group-rounded-bold-duotone' , ['Company View'] ,  'companies.add' ))
                ->add(new SidebarItem('Companies List' , 'solar-users-group-rounded-bold-duotone' , ['Company Create'] ,  'companies.index' ))
        );

        $sidebar->add(
            (new SidebarGroup('Students of the current semester', 'solar-users-group-rounded-bold-duotone' , ['Student View']))
                ->add(new SidebarItem('Add Students of the current semester' , 'solar-users-group-rounded-bold-duotone' , ['Student View'] ,  'students.index' ))
                ->add(new SidebarItem('Students of the current semester List' , 'solar-users-group-rounded-bold-duotone' , ['Student Create'] ,  'students.index' ))
        );

        $sidebar->add(
            (new SidebarGroup('Academic Courses', 'solar-book-bookmark-bold-duotone' , ['Course View']))
                ->add(new SidebarItem('Courses List' , 'solar-checklist-minimalistic-bold-duotone' , ['Course View'] ,  'courses.index' ))
        );

        $sidebar->add(
            (new SidebarItem('Attendance and departure log', 'solar-users-group-rounded-bold-duotone' , ['Student View'], 'students.index'))
        );

        $sidebar->add(
            (new SidebarItem('Follow-up file', 'solar-users-group-rounded-bold-duotone' , ['Student View'] , 'students.index'))
        );

        $sidebar->add(
            (new SidebarItem('Ratings', 'solar-users-group-rounded-bold-duotone' , ['Student View'] , 'students.index'))
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
