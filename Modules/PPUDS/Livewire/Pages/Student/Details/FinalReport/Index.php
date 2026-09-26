<?php

namespace Modules\PPUDS\Livewire\Pages\Student\Details\FinalReport;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Services\FinalReportService;
use Modules\PPUDS\Support\ScopesStudentCompanyVisibility;

/**
 * تبويب التقرير النهائي في تفاصيل الطالب: نفس العرض الذي يراه مشرف التقييم،
 * بصلاحية ونطاق شاشة تسليم التقارير النهائية.
 */
class Index extends Component
{
    use ScopesStudentCompanyVisibility;

    public ?int $studentId = null;

    public function mount(?int $studentId = null)
    {
        $this->authorize('Report View List');

        $this->studentId = $studentId;
    }

    /**
     * المشرف يرى تقارير تدريبات طلابه فقط، كما في شاشة تسليم التقارير النهائية.
     */
    #[Computed]
    public function finalReports(): Collection
    {
        return Registration::query()
            ->where('student_id', $this->studentId)
            ->whereHas('finalReport')
            ->unless(
                $this->currentUserIsAdmin(),
                fn (Builder $query): Builder => $query->whereHas(
                    'studentCompany',
                    fn (Builder $studentCompanyQuery): Builder => $this->applyStudentCompanyVisibilityScope($studentCompanyQuery)
                )
            )
            ->with([
                'finalReport.tasks',
                'finalReport.skills',
                'finalReport.items',
                'course',
                'supervisor',
                'media',
                'student.studentProfile',
                'studentCompany.company',
                'studentCompany.branch',
                'studentCompany.department',
                'studentCompany.student.studentProfile',
                'studentCompany.registration.supervisor',
            ])
            ->orderByDesc('id')
            ->get();
    }

    public function printFinalReport(int $registrationId)
    {
        $this->authorize('Report View List');

        $report = $this->finalReports->firstWhere('id', $registrationId)?->finalReport;

        if (! $report) {
            Toaster::error(__('No records found.'));

            return null;
        }

        return app(FinalReportService::class)->pdf($report);
    }

    public function render()
    {
        return view('ppuds::livewire.pages.student.details.final-report.index');
    }
}
