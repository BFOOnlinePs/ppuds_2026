<?php

namespace Modules\PPUDS\Providers;

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
            (new SidebarGroup('Students', 'solar-users-group-rounded-bold-duotone', ['Student View']))
                ->add(new SidebarItem('Students List', 'solar-users-group-rounded-bold-duotone', ['Student Create'], 'students.index'))
        );

        $sidebar->add(
            (new SidebarGroup('Specialties', 'solar-users-group-rounded-bold-duotone', ['Student View']))
                ->add(new SidebarItem('Specialties List', 'solar-users-group-rounded-bold-duotone', ['Student View'], 'majors.index'))
        );

        $sidebar->add(
            (new SidebarGroup('Registration', 'solar-users-group-rounded-bold-duotone', ['Registration View']))
                ->add(new SidebarItem('Add Registration', 'solar-users-group-rounded-bold-duotone', ['Registration View'], 'registrations.add'))
                ->add(new SidebarItem('Registration List', 'solar-users-group-rounded-bold-duotone', ['Registration Create'], 'registrations.index'))
        );

        $sidebar->add(
            (new SidebarGroup('Companies', 'solar-users-group-rounded-bold-duotone', ['Company View']))
                ->add(new SidebarItem('Companies Category List', 'solar-users-group-rounded-bold-duotone', ['Company Category View'], 'company-category.index'))
                ->add(new SidebarItem('Companies Department List', 'solar-users-group-rounded-bold-duotone', ['Company Department View'], 'company-department.index'))
                ->add(new SidebarItem('Add Company', 'solar-users-group-rounded-bold-duotone', ['Company View'], 'companies.add'))
                ->add(new SidebarItem('Companies List', 'solar-users-group-rounded-bold-duotone', ['Company Create'], 'companies.index'))
        );

        $sidebar->add(
            (new SidebarGroup('Academic Courses', 'solar-book-bookmark-bold-duotone', ['Course View']))
                ->add(new SidebarItem('Courses List', 'solar-checklist-minimalistic-bold-duotone', ['Course View'], 'courses.index'))
        );

        $sidebar->add(
            (new SidebarGroup('Student Companies', 'solar-users-group-rounded-bold-duotone', ['StudentCompany View']))
                ->add(new SidebarItem('Add Student Company', 'solar-users-group-rounded-bold-duotone', ['StudentCompany Create'], 'student-companies.add'))
                ->add(new SidebarItem('Student Companies List', 'solar-users-group-rounded-bold-duotone', ['StudentCompany View List'], 'student-companies.index'))
        );

        $sidebar->add(
            (new SidebarGroup('Field Visits', 'solar-clipboard-list-bold-duotone', ['FieldVisit View']))
                ->add(new SidebarItem('Add Field Visit', 'solar-clipboard-add-bold-duotone', ['FieldVisit View'], 'field-visits.add'))
                ->add(new SidebarItem('Field Visits List', 'solar-clipboard-list-bold-duotone', ['FieldVisit Create'], 'field-visits.index'))
        );

        $sidebar->add(
            (new SidebarGroup('Announcements', 'solar-users-group-rounded-bold-duotone', ['Announcement View']))
                ->add(new SidebarItem('Announcements List', 'solar-users-group-rounded-bold-duotone', ['Announcement View'], 'announcements.index'))
        );

        $sidebar->add(
            (new SidebarGroup('Leave Requests', 'solar-users-group-rounded-bold-duotone', ['LeaveRequest View List']))
                ->add(new SidebarItem('Leave Requests List', 'solar-users-group-rounded-bold-duotone', ['LeaveRequest View List'], 'leave-requests.index'))
        );

        $sidebar->add(
            (new SidebarGroup('Student Attendance', 'solar-users-group-rounded-bold-duotone', ['StudentAttendance View List']))
                ->add(new SidebarItem('Leave Requests List', 'solar-users-group-rounded-bold-duotone', ['StudentAttendance View List'], 'student-attendances.index'))
        );

        $sidebar->add(
            (new SidebarGroup('Surveys', 'solar-users-group-rounded-bold-duotone', ['Survey View List']))
                ->add(new SidebarItem('Surveys List', 'solar-users-group-rounded-bold-duotone', ['Survey View List'], 'surveys.index'))
                ->add(new SidebarItem('Add Survey', 'solar-users-group-rounded-bold-duotone', ['Survey Create'], 'surveys.add'))
        );

        $sidebar->add(
            (new SidebarGroup('Notes', 'solar-users-group-rounded-bold-duotone', ['Note View List']))
                ->add(new SidebarItem('Notes List', 'solar-users-group-rounded-bold-duotone', ['Note View List'], 'notes.index'))
                ->add(new SidebarItem('Add Note', 'solar-users-group-rounded-bold-duotone', ['Note Create'], 'notes.add'))
        );

        $sidebar->add(
            (new SidebarGroup('Chat Messages', 'solar-users-group-rounded-bold-duotone', ['Survey View List']))
                ->add(new SidebarItem('Chat Messages List', 'solar-users-group-rounded-bold-duotone', ['Survey View List'], 'chat-messages.index'))
                ->add(new SidebarItem('Add Chat Message', 'solar-users-group-rounded-bold-duotone', ['Survey View Create'], 'chat-messages.add'))
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
