<?php

namespace Modules\PPUDS\Livewire\Pages\Student\Details\Statistics;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Modules\Core\Entities\User;
use Modules\PPUDS\Entities\FieldVisit;
use Modules\PPUDS\Entities\LeaveRequest;
use Modules\PPUDS\Entities\Payment;
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Entities\StudentAttendance;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Entities\StudentReport;
use Modules\PPUDS\Entities\WorkExperience;
use Modules\PPUDS\Enums\AttendanceStatus;
use Modules\PPUDS\Enums\LeaveRequestStatus;
use Modules\PPUDS\Enums\PaymentStatus;
use Modules\PPUDS\Enums\TrainingStatus;
use Modules\PPUDS\Support\ScopesStudentCompanyVisibility;

/**
 * Read-only statistics panel for one student, shown as the first tab of the
 * student details page. It aggregates every record type attached to the
 * student: trainings, registrations, attendance, field visits, leave
 * requests, daily reports, payments and work experience.
 */
class Index extends Component
{
    use ScopesStudentCompanyVisibility;

    public int $studentId;

    public function mount(int $studentId): void
    {
        abort_unless($this->canAccessStudentUser($studentId), 403);

        $this->studentId = $studentId;
    }

    #[Computed]
    public function student(): ?User
    {
        return User::with('studentProfile.major')->find($this->studentId);
    }

    /**
     * Every StudentCompany row for this student, already narrowed to what the
     * viewer is allowed to see. Cloned per aggregate rather than reused, since
     * each count applies its own constraints.
     */
    protected function studentCompaniesQuery(): Builder
    {
        return StudentCompany::query()
            ->where('student_id', $this->studentId)
            ->tap(fn (Builder $query): Builder => $this->applyStudentCompanyVisibilityScope($query));
    }

    /** Ids only — the child aggregates join through these. */
    #[Computed]
    public function studentCompanyIds(): array
    {
        return $this->studentCompaniesQuery()->pluck('id')->all();
    }

    protected function attendanceQuery(): Builder
    {
        return StudentAttendance::query()->whereIn('student_company_id', $this->studentCompanyIds());
    }

    #[Computed]
    public function trainings(): array
    {
        $base = $this->studentCompaniesQuery();

        return [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('status', TrainingStatus::AVAILABLE->value)->count(),
            'finished' => (clone $base)->where('status', TrainingStatus::FINISHED->value)->count(),
            'companies' => (clone $base)->whereNotNull('company_id')->distinct()->count('company_id'),
        ];
    }

    #[Computed]
    public function attendance(): array
    {
        $base = $this->attendanceQuery();
        $checkedIn = (clone $base)->whereNotNull('check_in')->count();

        return [
            'days' => (clone $base)->whereNotNull('check_in')->distinct()->count('attendance_date'),
            'records' => $checkedIn,
            'open' => (clone $base)->whereNotNull('check_in')->whereNull('check_out')->count(),
            'approved' => (clone $base)->where('status', AttendanceStatus::APPROVED->value)->count(),
            'discrepancy' => (clone $base)->where('status', AttendanceStatus::DISCREPANCY->value)->count(),
            'undetermined' => (clone $base)->where('status', AttendanceStatus::UNDETERMINED->value)->count(),
            'working_hours' => $this->workingHours(),
            'last_at' => (clone $base)->whereNotNull('check_in')->max('attendance_date'),
        ];
    }

    /**
     * Sums completed check-in/check-out pairs. Discrepancy rows are excluded
     * to match how StudentCompany::withActualWorkingHours() reports hours, so
     * the two never disagree on the same student.
     */
    protected function workingHours(): float
    {
        $minutes = (float) $this->attendanceQuery()
            ->whereNotNull('check_in')
            ->whereNotNull('check_out')
            ->where('status', '!=', AttendanceStatus::DISCREPANCY->value)
            ->sum(DB::raw('TIMESTAMPDIFF(MINUTE, check_in, check_out)'));

        return round($minutes / 60, 2);
    }

    #[Computed]
    public function fieldVisits(): array
    {
        $base = FieldVisit::query()->whereIn('student_company_id', $this->studentCompanyIds());

        return [
            'total' => (clone $base)->count(),
            'total_minutes' => (int) (clone $base)->sum('visit_duration'),
            'last_at' => (clone $base)->max('visit_date'),
        ];
    }

    #[Computed]
    public function leaveRequests(): array
    {
        $base = LeaveRequest::query()->whereIn('student_company_id', $this->studentCompanyIds());

        return [
            'total' => (clone $base)->count(),
            'pending' => (clone $base)->where('university_approval', LeaveRequestStatus::PENDING->value)->count(),
            'approved' => (clone $base)->where('university_approval', LeaveRequestStatus::APPROVED->value)->count(),
            'rejected' => (clone $base)->where('university_approval', LeaveRequestStatus::REJECTED->value)->count(),
        ];
    }

    #[Computed]
    public function reports(): array
    {
        $base = StudentReport::query()->whereHas(
            'studentAttendance',
            fn (Builder $query): Builder => $query->whereIn('student_company_id', $this->studentCompanyIds())
        );

        return [
            'total' => (clone $base)->count(),
            'today' => (clone $base)->whereDate('created_at', now()->toDateString())->count(),
            'last_at' => (clone $base)->max('created_at'),
        ];
    }

    #[Computed]
    public function payments(): array
    {
        $base = Payment::query()->whereIn('student_company_id', $this->studentCompanyIds());

        return [
            'total' => (clone $base)->count(),
            'paid' => (clone $base)->where('status', PaymentStatus::PAID->value)->count(),
            'unpaid' => (clone $base)->where('status', PaymentStatus::UNPAID->value)->count(),
            'paid_amount' => (float) (clone $base)->where('status', PaymentStatus::PAID->value)->sum('payment_value'),
            'total_amount' => (float) (clone $base)->sum('payment_value'),
        ];
    }

    /**
     * The money side of the placements, kept per company so the panel can
     * answer "how much did this student actually receive from each company".
     * Rows are grouped by company *and* currency: placements paid in
     * different currencies must never be summed into a single figure.
     */
    #[Computed]
    public function financialRecord(): array
    {
        $payments = Payment::query()
            ->with([
                'studentCompany.company.translations',
                'studentCompany.branch.translations',
                'currency.translations',
            ])
            ->whereIn('student_company_id', $this->studentCompanyIds())
            ->latest('id')
            ->get();

        return [
            'entries' => $payments,
            'companies' => $payments
                ->groupBy(fn (Payment $payment): string => $payment->studentCompany?->company_id . ':' . $payment->currency_id)
                ->map(fn (Collection $group): array => [
                    'company' => $group->first()->studentCompany?->company?->name ?: __('Unknown'),
                    'currency' => $group->first()->currency?->name ?: '—',
                    'records' => $group->count(),
                    'paid' => $this->sumPaymentsByStatus($group, PaymentStatus::PAID),
                    'unpaid' => $this->sumPaymentsByStatus($group, PaymentStatus::UNPAID),
                    'total' => (float) $group->sum('payment_value'),
                ])
                ->sortByDesc('paid')
                ->values()
                ->all(),
            'received' => $payments
                ->filter(fn (Payment $payment): bool => $payment->status === PaymentStatus::PAID)
                ->groupBy('currency_id')
                ->map(fn (Collection $group): array => [
                    'currency' => $group->first()->currency?->name ?: '—',
                    'amount' => (float) $group->sum('payment_value'),
                ])
                ->values()
                ->all(),
        ];
    }

    /** Sums one payment status inside an already loaded collection. */
    protected function sumPaymentsByStatus(Collection $payments, PaymentStatus $status): float
    {
        return (float) $payments
            ->filter(fn (Payment $payment): bool => $payment->status === $status)
            ->sum('payment_value');
    }

    #[Computed]
    public function registrations(): array
    {
        $base = Registration::query()->where('student_id', $this->studentId);

        return [
            'total' => (clone $base)->count(),
            'latest' => (clone $base)->with(['course', 'supervisor'])->latest('id')->first(),
            'average_university_score' => round((float) (clone $base)->avg('university_score'), 2),
            'average_company_score' => round((float) (clone $base)->avg('company_score'), 2),
        ];
    }

    #[Computed]
    public function workExperience(): array
    {
        $base = WorkExperience::query()->where('user_id', $this->studentId);

        return [
            'total' => (clone $base)->count(),
            'current' => (clone $base)->where('is_current', true)->count(),
        ];
    }

    /** The most recent placement, used for the summary strip under the cards. */
    #[Computed]
    public function currentTraining(): ?StudentCompany
    {
        return $this->studentCompaniesQuery()
            ->with([
                'company.translations',
                'branch.translations',
                'department.translations',
                'registration.course.translations',
                'registration.supervisor',
            ])
            ->latest('id')
            ->first();
    }

    public function render()
    {
        return view('ppuds::livewire.pages.student.details.statistics.index');
    }
}
