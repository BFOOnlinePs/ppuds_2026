<?php

namespace Modules\PPUDS\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Enums\UserRole;
use Modules\Core\Services\SidebarGroup;
use Modules\Core\Services\SidebarItem;
use Modules\Core\Services\SidebarService;
use Modules\PPUDS\Services\FinalReportService;

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

        // ترتيب القائمة حسب مسار العمل، وكل قسم له نطاق ترتيب خاص به:
        // 10-99 العام، 100 إدارة التدريب، 200 التقييم والتقارير،
        // 300 الشركات والمشرفون، 400 التواصل، 500 إدارة النظام (موديول Core).

        // $sidebar->add(
        //     (new SidebarItem('Profile', 'solar-users-group-rounded-bold-duotone', [] ,  'students.details' , 1))
        // );

        $sidebar->add(
            (new SidebarItem('Messages', 'solar-chat-round-dots-bold-duotone', [], 'chat-messages.index', 20))
                ->hiddenForRoles([UserRole::EVALUATION_SUPERVISOR->value])
        );

        // ===== إدارة التدريب العملي =====

        $sidebar->add(
            (new SidebarGroup('Students', 'solar-square-academic-cap-bold-duotone', [], 100))
                ->section('Training Management')
                ->add(new SidebarItem('Students List', 'solar-users-group-rounded-bold-duotone', ['Student View List'], 'students.index'))
                ->add(new SidebarItem('Practical Supervisor Students', 'solar-user-id-bold-duotone', ['PracticalSupervisorStudent View List'], 'practical-supervisor-students.index'))
                ->add(new SidebarItem('Evaluation Supervisor Students', 'solar-star-bold-duotone', ['EvaluationSupervisorStudent View List'], 'evaluation-supervisor-students.index'))
        );

        $sidebar->add(
            (new SidebarGroup('Registration', 'solar-clipboard-check-bold-duotone', [], 110))
                ->section('Training Management')
                ->add(new SidebarItem('Registration List', 'solar-list-check-bold-duotone', ['Registration View List'], 'registrations.index'))
            // ->add(new SidebarItem('Add Registration', 'solar-users-group-rounded-bold-duotone', ['Registration Create'], 'registrations.add'))
        );

        $sidebar->add(
            (new SidebarGroup('Student Companies', 'solar-hand-shake-bold-duotone', [], 120))
                ->section('Training Management')
                ->add(new SidebarItem('Student Companies List', 'solar-clipboard-list-bold-duotone', ['StudentCompany View List'], 'student-companies.index'))
                ->add(new SidebarItem('Add Student Company', 'solar-clipboard-add-bold-duotone', ['StudentCompany Create'], 'student-companies.add'))
                ->add(new SidebarItem('Import Placements', 'solar-document-add-bold-duotone', ['StudentCompany Create'], 'student-companies.import'))
        );

        $sidebar->add(
            (new SidebarGroup('Attendance And Departure', 'solar-clock-circle-bold-duotone', [], 130))
                ->section('Training Management')
                ->add(new SidebarItem('Attendance And Departure Log', 'solar-history-3-bold-duotone', ['StudentAttendance View List'], 'student-attendances.index'))
                ->add(new SidebarItem('Attendance And Departure Permissions', 'solar-shield-check-bold-duotone', ['LeaveRequest View List'], 'leave-requests.index'))
        );

        $sidebar->add(
            (new SidebarGroup('Field Visits', 'solar-map-point-wave-bold-duotone', [], 140))
                ->section('Training Management')
                ->add(new SidebarItem('Field Visits List', 'solar-map-point-bold-duotone', ['FieldVisit View List'], 'field-visits.index'))
                ->add(new SidebarItem('Add Field Visit', 'solar-map-point-add-bold-duotone', ['FieldVisit Create'], 'field-visits.add'))
        );

        // تسليم التقرير النهائي — تظهر للطالب وحده لأن صلاحيتها ممنوحة لدوره فقط،
        // وتختفي إذا كانت حالة التقارير في الإعدادات مغلقة.
        $sidebar->add(
            (new SidebarItem('Final Report Submission', 'solar-diploma-verified-bold-duotone', ['FinalReport View'], 'final-reports.index', 150))
                ->section('Training Management')
                ->visible(fn (): bool => app(FinalReportService::class)->submissionIsOpen())
        );

        $sidebar->add(
            (new SidebarGroup('My Notes', 'solar-notes-bold-duotone', [], 160))
                ->section('Training Management')
                ->add(new SidebarItem('Notes List', 'solar-notebook-bold-duotone', ['Note View List'], 'notes.index'))
                ->add(new SidebarItem('Add Note', 'solar-document-add-bold-duotone', ['Note Create'], 'notes.add'))
        );

        // ===== التقييم والتقارير =====

        // العلامات: شاشة عرض مجمّعة (بدون تعديل) وشاشة وضع علامة مشرف الجامعة.
        // وضع علامة مشرف التقييم يبقى في شاشة "طلاب مشرف التقييم" ضمن مجموعة الطلاب.
        $sidebar->add(
            (new SidebarGroup('Grades', 'solar-ranking-bold-duotone', [], 200))
                ->section('Evaluation And Reports')
                ->add(new SidebarItem('Student Grades', 'solar-diploma-verified-bold-duotone', ['StudentGrade View List'], 'student-grades.index'))
                ->add(new SidebarItem('University Supervisor Grades', 'solar-star-bold-duotone', ['PracticalSupervisorStudent Grade'], 'practical-supervisor-grades.index'))
        );

        $sidebar->add(
            (new SidebarGroup('Reports', 'solar-chart-square-bold-duotone', [], 210))
                ->section('Evaluation And Reports')
                ->add(new SidebarItem('Report List', 'solar-presentation-graph-bold-duotone', ['Report View List'], 'reports.index'))
                ->add(new SidebarItem('Today Reports', 'solar-document-text-bold-duotone', ['Report View List'], 'reports.today'))
                ->add(new SidebarItem('Absence Report', 'solar-calendar-search-bold-duotone', ['Report View List'], 'absence-reports.index'))
                ->add(new SidebarItem('Non Compliance Report', 'solar-calendar-search-bold-duotone', ['Report View List'], 'non-compliance-reports.index'))
                ->add(new SidebarItem('Statistics', 'solar-chart-2-bold-duotone', ['Report View List'], 'company-statistics.index'))
                ->add(new SidebarItem('Final Delivery Report', 'solar-diploma-verified-bold-duotone', ['Report View List'], 'final-delivery-reports.index'))
                ->add(new SidebarItem('Supervisor Report', 'solar-user-speak-rounded-bold-duotone', ['Supervisor Report View List'], 'supervisor-reports.index'))
        );

        // ===== الشركات والمشرفون =====

        $sidebar->add(
            (new SidebarGroup('Companies', 'solar-buildings-bold-duotone', [], 300))
                ->section('Companies And Supervisors')
                ->add((new SidebarItem('Companies List', 'solar-buildings-3-bold-duotone', ['Company View List'], 'companies.index'))
                    ->hiddenForRoles($this->companyRoles()))
                ->add(new SidebarItem('Add Company', 'solar-add-square-bold-duotone', ['Company Create'], 'companies.add'))
                ->add((new SidebarItem('Company Supervisors', 'solar-users-group-rounded-bold-duotone', ['Company View List'], 'company-supervisors.index'))
                    ->hiddenForRoles($this->companyRoles()))
                ->add(new SidebarItem('Companies Category List', 'solar-widget-4-bold-duotone', ['Company Category View List'], 'company-category.index'))
                ->add(new SidebarItem('Companies Department List', 'solar-structure-bold-duotone', ['Company Department View List'], 'company-department.index'))
        );

        $sidebar->add(
            (new SidebarGroup('University Supervisors', 'solar-user-speak-rounded-bold-duotone', [], 310))
                ->section('Companies And Supervisors')
                ->add(new SidebarItem('University Supervisors List', 'solar-user-id-bold-duotone', ['Supervisor View List'], 'supervisors.index'))
        );

        $sidebar->add(
            (new SidebarGroup('Evaluation Supervisors', 'solar-medal-star-bold-duotone', [], 320))
                ->section('Companies And Supervisors')
                ->add(new SidebarItem('Evaluation Supervisors List', 'solar-user-check-rounded-bold-duotone', ['EvaluationSupervisor View List'], 'evaluation-supervisors.index'))
        );

        // ===== التواصل والإعلانات =====

        $sidebar->add(
            (new SidebarGroup('Announcements', 'solar-bell-bing-bold-duotone', [], 400))
                ->section('Communication And Announcements')
                ->add(new SidebarItem('Announcements List', 'solar-notification-unread-lines-bold-duotone', ['Announcement View List'], 'announcements.index'))
                ->add(new SidebarItem('Announcement Categories', 'solar-widget-4-bold-duotone', ['Announcement Category View List'], 'announcement-category.index'))
        );

        $sidebar->add(
            (new SidebarItem('Banners', 'solar-gallery-wide-bold-duotone', ['Banner View List'], 'banners.index', 410))
                ->section('Communication And Announcements')
        );

        $sidebar->add(
            (new SidebarGroup('Surveys', 'solar-checklist-bold-duotone', [], 420))
                ->section('Communication And Announcements')
                ->add(new SidebarItem('Surveys List', 'solar-checklist-minimalistic-bold-duotone', ['Survey View List'], 'surveys.index'))
                ->add(new SidebarItem('Add Survey', 'solar-document-add-bold-duotone', ['Survey Create'], 'surveys.add'))
        );

        $sidebar->add(
            (new SidebarGroup('Alumni', 'solar-square-academic-cap-bold-duotone', [], 430))
                ->section('Communication And Announcements')
                ->add(new SidebarItem('Alumni Report', 'solar-chart-2-bold-duotone', ['Alumni Report View List'], 'alumni-report.index'))
                ->add(new SidebarItem('Alumni Announcements', 'solar-notification-unread-lines-bold-duotone', ['Alumni Announcement View List'], 'alumni-announcements.index'))
        );
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [];
    }

    private function companyRoles(): array
    {
        return [
            UserRole::COMPANY_SUPERVISOR->value,
            'Company Manager',
            'مدير الشركة',
        ];
    }
}
