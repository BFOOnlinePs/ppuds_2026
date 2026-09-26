<?php

namespace Modules\PPUDS\Livewire\Pages\EvaluationSupervisorStudent\Details;

use App\View\Components\AppLayout;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Enums\LeaveRequestStatus;
use Modules\PPUDS\Services\AbsenceReportService;
use Modules\PPUDS\Services\FinalReportService;
use Modules\PPUDS\Settings\GeneralSettings;

/**
 * السجل الكامل لطالب كما يراه مشرف التقييم: بياناته، وكل تدريب مرّ به ولو
 * انتقل بين أكثر من شركة، وتفصيل أيام دوامه وغيابه وإجازاته في كل تدريب،
 * ثم سجلات الحضور والتقارير اليومية والإجازات وتقريره النهائي كما سلّمه.
 */
class Details extends Component
{
    public int $studentId;

    #[Url(as: 'tab', keep: true)]
    public string $tab = 'overview';

    /** ملخص الغياب مكلف الحساب، فيُحسب مرة واحدة لكل تدريب في الطلب الواحد. */
    private array $absenceSummaries = [];

    public function mount(User $user): void
    {
        // الدخول لمن أُسند له الطالب فقط، فلا يُكشف طالب غيره بتغيير الرقم في الرابط.
        abort_unless($this->accessiblePlacementsQuery($user->id)->exists(), 404);

        $this->studentId = $user->id;

        $this->selectTab($this->tab);
    }

    public function selectTab(string $tab): void
    {
        $this->tab = array_key_exists($tab, $this->tabs()) ? $tab : 'overview';
    }

    /**
     * تبويبات السجل بالترتيب الذي يُقرأ به: الملخص أولاً ثم السجلات التفصيلية.
     *
     * @return array<string, array<string, string>>
     */
    public function tabs(): array
    {
        return [
            'overview' => ['label' => __('Overview'), 'icon' => 'solar-chart-square-bold-duotone'],
            'trainings' => ['label' => __('Training History'), 'icon' => 'solar-city-bold-duotone'],
            'attendance' => ['label' => __('Attendance'), 'icon' => 'solar-calendar-mark-bold-duotone'],
            'daily-reports' => ['label' => __('Daily Reports'), 'icon' => 'solar-document-text-bold-duotone'],
            'leave-requests' => ['label' => __('Leave Requests'), 'icon' => 'solar-calendar-date-bold-duotone'],
            'final-report' => ['label' => __('Final Report'), 'icon' => 'solar-notebook-bold-duotone'],
        ];
    }

    #[Computed]
    public function student(): User
    {
        return User::with(['studentProfile.major', 'media'])->findOrFail($this->studentId);
    }

    /**
     * كل تدريبات الطالب وليس المسندة لهذا المشرف وحدها: الصلاحية تُمنح بوجود
     * تدريب واحد مسند إليه، ثم يُعرض السجل كاملاً لأن تقييمه يحتاج المسار كله.
     */
    #[Computed]
    public function placements(): Collection
    {
        return StudentCompany::query()
            ->where('student_id', $this->studentId)
            ->with([
                'company',
                'branch',
                'branch.workingHours',
                'department',
                'attendances',
                'workingHours',
                'leaveRequests',
                'evaluationSupervisor',
                'registration.course',
                'registration.supervisor',
            ])
            ->withAttendanceDays()
            ->withActualWorkingHours()
            ->orderByDesc('id')
            ->get();
    }

    /**
     * تفصيل أيام الدوام لتدريب واحد بنفس حساب شاشة تقرير الغياب، فلا يختلف
     * رقمٌ هنا عن رقمه هناك.
     *
     * @return array<string, mixed>
     */
    public function absence(StudentCompany $placement): array
    {
        return $this->absenceSummaries[$placement->id] ??= app(AbsenceReportService::class)
            ->detailedSummary($placement);
    }

    /**
     * إجازات التدريب الواحد: عدد الطلبات وحالاتها، وأيامها كما تحتسبها شاشة الغياب.
     *
     * @return array<string, int>
     */
    public function leaves(StudentCompany $placement): array
    {
        $requests = $placement->leaveRequests;

        return [
            'total' => $requests->count(),
            'approved' => $requests->filter->isFullyApproved()->count(),
            'pending' => $requests->where('university_approval', LeaveRequestStatus::PENDING)->count(),
            'rejected' => $requests->where('university_approval', LeaveRequestStatus::REJECTED)->count(),
            'days' => (int) $this->absence($placement)['leave_request_days'],
        ];
    }

    /**
     * مجاميع مسيرة الطالب كلها، لا تدريبه الأخير وحده.
     *
     * @return array<string, int|float>
     */
    #[Computed]
    public function totals(): array
    {
        $placements = $this->placements;
        $absences = $placements->map(fn (StudentCompany $placement): array => $this->absence($placement));

        return [
            'trainings' => $placements->count(),
            'companies' => $placements->whereNotNull('company_id')->pluck('company_id')->unique()->count(),
            'required_days' => (int) $absences->sum('required_working_days'),
            'attendance_days' => (int) $placements->sum('attendance_days'),
            'working_hours' => round((float) $placements->sum('actual_working_hours'), 2),
            'absence_days' => (int) $absences->sum('total_absence_days'),
            'excused_days' => (int) $absences->sum('excused_absence_days'),
            'unexcused_days' => (int) $absences->sum('unexcused_absence_days'),
            'leave_requests' => (int) $placements->sum(fn (StudentCompany $placement): int => $placement->leaveRequests->count()),
            'leave_days' => (int) $absences->sum('leave_request_days'),
        ];
    }

    /**
     * التقارير النهائية لكل فصل سجّل فيه الطالب، مرتبطاً كلٌّ منها بتدريبه.
     */
    #[Computed]
    public function finalReports(): Collection
    {
        return Registration::query()
            ->where('student_id', $this->studentId)
            ->whereHas('finalReport')
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

    /**
     * نفس ملف PDF الذي يطبعه الطالب. البحث داخل تقارير هذا الطالب وحده، فلا
     * يُطبع تقرير طالب آخر بتمرير رقم تسجيله.
     */
    public function printFinalReport(int $registrationId)
    {
        $report = $this->finalReports->firstWhere('id', $registrationId)?->finalReport;

        if (! $report) {
            Toaster::error(__('No records found.'));

            return null;
        }

        return app(FinalReportService::class)->pdf($report);
    }

    /**
     * علامة الشركة: المحسوبة من استبيان مشرف الشركة متى وُجدت، وإلا المستوردة
     * من مزامنة نظام الجامعة — نفس ترتيب شاشة علامات الطلاب.
     */
    public function companyGrade(StudentCompany $placement): int|float|null
    {
        return $placement->company_survey_score ?? $placement->registration?->company_score;
    }

    public function totalGrade(StudentCompany $placement): int|float|null
    {
        $scores = [
            $placement->evaluation_score,
            $placement->supervisor_score,
            $this->companyGrade($placement),
        ];

        $recorded = array_filter($scores, fn ($score): bool => $score !== null);

        return $recorded === [] ? null : array_sum($recorded);
    }

    public function formatGrade(int|float|null $score, int $maxGrade): string
    {
        if ($score === null) {
            return __('Not graded yet');
        }

        return ((float) $score == (int) $score ? (int) $score : round((float) $score, 2)).' / '.$maxGrade;
    }

    public function evaluationMaxGrade(): int
    {
        return app(GeneralSettings::class)->evaluation_supervisor_max_grade;
    }

    public function universityMaxGrade(): int
    {
        return app(GeneralSettings::class)->university_supervisor_max_grade;
    }

    public function companyMaxGrade(): int
    {
        return app(GeneralSettings::class)->company_max_grade;
    }

    public function totalMaxGrade(): int
    {
        return $this->evaluationMaxGrade() + $this->universityMaxGrade() + $this->companyMaxGrade();
    }

    /** هل هذا التدريب من نصيب المشرف الذي يطالع السجل. */
    public function isAssignedToViewer(StudentCompany $placement): bool
    {
        return $placement->evaluation_supervisor_id !== null
            && (int) $placement->evaluation_supervisor_id === (int) auth()->id();
    }

    /** التدريبات التي يملك المشرف رصد علامتها، وهي شرط فتح السجل أصلاً. */
    protected function accessiblePlacementsQuery(int $studentId): Builder
    {
        return StudentCompany::query()
            ->where('student_id', $studentId)
            ->when(
                $this->shouldScopeToAuthenticatedSupervisor(),
                fn (Builder $query): Builder => $query->where('evaluation_supervisor_id', auth()->id())
            );
    }

    protected function shouldScopeToAuthenticatedSupervisor(): bool
    {
        $user = auth()->user();

        return (bool) (
            $user?->hasRole(UserRole::EVALUATION_SUPERVISOR->value)
            && ! $user?->hasAnyRole([
                UserRole::SUPER_ADMIN->value,
                UserRole::ADMIN->value,
            ])
        );
    }

    public function render()
    {
        return view('ppuds::livewire.pages.evaluation-supervisor-student.details.details')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Evaluation Supervisor Students'), 'url' => route('evaluation-supervisor-students.index')],
                ['title' => $this->student->name, 'url' => route('evaluation-supervisor-students.details', $this->studentId)],
            ],
        ]);
    }
}
