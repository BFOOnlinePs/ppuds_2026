<?php

namespace Modules\PPUDS\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Enums\UserRole;
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
            return new SidebarService;
        });
    }

    public function boot(): void
    {
        $sidebar = $this->app->make(SidebarService::class);

        // $sidebar->add(
        //     (new SidebarItem('Profile', 'solar-users-group-rounded-bold-duotone', [] ,  'students.details' , 1))
        // );

        $sidebar->add(
            new SidebarItem('Messages', 'solar-users-group-rounded-bold-duotone', [], 'chat-messages.index', 20)
        );

        $sidebar->add(
            (new SidebarGroup('Companies', 'solar-users-group-rounded-bold-duotone', [], 40))
                ->exceptRoles([UserRole::STUDENT->value])
                ->add(new SidebarItem('Companies List', 'solar-users-group-rounded-bold-duotone', ['Company View List'], 'companies.index'))
                ->add(new SidebarItem('Add Company', 'solar-users-group-rounded-bold-duotone', ['Company Create'], 'companies.add'))
                ->add(new SidebarItem('Companies Category List', 'solar-users-group-rounded-bold-duotone', ['Company Category View List'], 'company-category.index'))
                ->add(new SidebarItem('Companies Department List', 'solar-users-group-rounded-bold-duotone', ['Company Department View List'], 'company-department.index'))
        );

        $sidebar->add(
            (new SidebarGroup('Students', 'solar-users-group-rounded-bold-duotone', [], 50))
                ->add(new SidebarItem('Students List', 'solar-users-group-rounded-bold-duotone', ['Student View List'], 'students.index'))
        );

        $sidebar->add(
            (new SidebarGroup('University Supervisors', 'solar-user-id-bold-duotone', [], 55))
                ->add(new SidebarItem('University Supervisors List', 'solar-user-id-bold-duotone', ['Supervisor View List'], 'supervisors.index'))
        );

        $sidebar->add(
            (new SidebarGroup('Registration', 'solar-users-group-rounded-bold-duotone', [], 60))
                ->add(new SidebarItem('Registration List', 'solar-users-group-rounded-bold-duotone', ['Registration View List'], 'registrations.index'))
                ->add(new SidebarItem('Add Registration', 'solar-users-group-rounded-bold-duotone', ['Registration Create'], 'registrations.add'))
        );

        $sidebar->add(
            (new SidebarGroup('Student Companies', 'solar-users-group-rounded-bold-duotone', [], 70))
                ->add(new SidebarItem('Student Companies List', 'solar-users-group-rounded-bold-duotone', ['StudentCompany View List'], 'student-companies.index'))
                ->add(new SidebarItem('Add Student Company', 'solar-users-group-rounded-bold-duotone', ['StudentCompany Create'], 'student-companies.add'))
        );

        $sidebar->add(
            (new SidebarGroup('Field Visits', 'solar-clipboard-list-bold-duotone', [], 80))
                ->add(new SidebarItem('Field Visits List', 'solar-clipboard-list-bold-duotone', ['FieldVisit View List'], 'field-visits.index'))
                ->add(new SidebarItem('Add Field Visit', 'solar-clipboard-add-bold-duotone', ['FieldVisit Create'], 'field-visits.add'))
        );

        $sidebar->add(
            (new SidebarGroup('Attendance And Departure', 'solar-users-group-rounded-bold-duotone', [], 90))
                ->add(new SidebarItem('Attendance And Departure Log', 'solar-users-group-rounded-bold-duotone', ['StudentAttendance View List'], 'student-attendances.index'))
                ->add(new SidebarItem('Attendance And Departure Permissions', 'solar-users-group-rounded-bold-duotone', ['LeaveRequest View List'], 'leave-requests.index'))
        );

        $sidebar->add(
            (new SidebarGroup('Announcements', 'solar-users-group-rounded-bold-duotone', [], 100))
                ->add(new SidebarItem('Announcements List', 'solar-users-group-rounded-bold-duotone', ['Announcement View List'], 'announcements.index'))
        );

        $sidebar->add(
            (new SidebarGroup('Surveys', 'solar-users-group-rounded-bold-duotone', [], 110))
                ->add(new SidebarItem('Surveys List', 'solar-users-group-rounded-bold-duotone', ['Survey View List'], 'surveys.index'))
                ->add(new SidebarItem('Add Survey', 'solar-users-group-rounded-bold-duotone', ['Survey Create'], 'surveys.add'))
        );

        $sidebar->add(
            (new SidebarGroup('Reports', 'solar-users-group-rounded-bold-duotone', [], 120))
                ->add(new SidebarItem('Report List', 'solar-users-group-rounded-bold-duotone', ['Report View List'], 'reports.index'))
        );

        $sidebar->add(
            (new SidebarGroup('My Notes', 'solar-users-group-rounded-bold-duotone', [], 130))
                ->add(new SidebarItem('Notes List', 'solar-users-group-rounded-bold-duotone', ['Note View List'], 'notes.index'))
                ->add(new SidebarItem('Add Note', 'solar-users-group-rounded-bold-duotone', ['Note Create'], 'notes.add'))
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
