<?php

namespace Modules\PPUDS\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Modules\Core\Traits\ApiResponse;
use Modules\PPUDS\Entities\FieldVisit;
use Modules\PPUDS\Entities\LeaveRequest;
use Modules\PPUDS\Entities\Major;
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Entities\StudentAttendance;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Entities\StudentReport;
use Modules\PPUDS\Enums\AttendanceStatus;
use Modules\PPUDS\Enums\LeaveRequestStatus;
use Modules\PPUDS\Enums\SemesterType;
use Modules\PPUDS\Enums\StudentGender;
use Modules\PPUDS\Enums\TrainingStatus;
use Modules\PPUDS\Http\Requests\SupervisorStatisticsRequest;
use Modules\PPUDS\Settings\GeneralSettings;
use Modules\PPUDS\Support\ScopesStudentCompanyVisibility;

/**
 * @OA\Tag(
 * name="Supervisor Statistics",
 * description="API Endpoints for supervisor statistics"
 * )
 */
class SupervisorStatisticsController extends Controller
{
    use ApiResponse;
    use ScopesStudentCompanyVisibility;

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/supervisors/statistics",
     * summary="Get supervisor statistics",
     * tags={"Supervisor Statistics"},
     * security={{"sanctum": {}}},
     *
     * @OA\Parameter(name="filter[supervisor_id]", in="query", required=false, @OA\Schema(type="integer", example=3)),
     * @OA\Parameter(name="filter[semester]", in="query", required=false, @OA\Schema(type="integer", example=1)),
     * @OA\Parameter(name="filter[year]", in="query", required=false, @OA\Schema(type="integer", example=2026)),
     * @OA\Parameter(name="filter[date_from]", in="query", required=false, @OA\Schema(type="string", format="date", example="2026-07-01")),
     * @OA\Parameter(name="filter[date_to]", in="query", required=false, @OA\Schema(type="string", format="date", example="2026-07-31")),
     *
     * @OA\Response(response=200, description="Supervisor statistics retrieved successfully")
     * )
     */
    public function index(SupervisorStatisticsRequest $request)
    {
        $settings = app(GeneralSettings::class);
        $supervisorId = $this->supervisorId($request);
        $semester = (int) $request->input('filter.semester', $settings->semester_type->value);
        $year = (int) $request->input('filter.year', $settings->year);
        $dateFrom = $request->input('filter.date_from');
        $dateTo = $request->input('filter.date_to');

        $studentCompanies = $this->studentCompaniesQuery($supervisorId, $semester, $year);
        $registrations = $this->registrationsQuery($supervisorId, $semester, $year);
        $attendances = $this->applyDateRange(
            $this->attendanceQuery($studentCompanies),
            'attendance_date',
            $dateFrom,
            $dateTo
        );
        $fieldVisits = $this->applyDateRange(
            $this->fieldVisitsQuery($studentCompanies, $supervisorId),
            'visit_date',
            $dateFrom,
            $dateTo
        );
        $leaveRequests = $this->applyDateRange(
            $this->leaveRequestsQuery($studentCompanies),
            'start_at',
            $dateFrom,
            $dateTo
        );
        $studentReports = $this->applyDateRange(
            $this->studentReportsQuery($studentCompanies),
            'created_at',
            $dateFrom,
            $dateTo
        );

        return $this->successResponse([
            'supervisor_id' => $supervisorId,
            'filters' => [
                'semester' => $semester,
                'semester_label' => SemesterType::tryFrom($semester)?->getLabel(),
                'year' => $year,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'summary' => $this->summary(
                $studentCompanies,
                $registrations,
                $attendances,
                $fieldVisits,
                $leaveRequests,
                $studentReports
            ),
            'charts' => [
                'training_statuses' => $this->enumStatistics(TrainingStatus::cases(), $studentCompanies, 'status'),
                'attendance_statuses' => $this->enumStatistics(AttendanceStatus::cases(), $attendances, 'status'),
                'attendance_last_seven_days' => $this->attendanceLastSevenDays($attendances),
                'field_visits_last_six_months' => $this->fieldVisitsLastSixMonths($fieldVisits),
                'leave_requests_statuses' => $this->leaveRequestStatusStatistics($leaveRequests),
                'students_by_gender' => $this->studentsByGender($registrations),
                'students_by_major' => $this->studentsByMajor($registrations),
            ],
        ], __('Supervisor statistics retrieved successfully'));
    }

    private function supervisorId(SupervisorStatisticsRequest $request): ?int
    {
        $requestedSupervisorId = $request->input('filter.supervisor_id');

        if ($this->currentUserIsAdmin()) {
            return filled($requestedSupervisorId) ? (int) $requestedSupervisorId : null;
        }

        abort_unless($this->shouldScopeUniversitySupervisorStudentCompanies(), 403);

        if (filled($requestedSupervisorId) && (int) $requestedSupervisorId !== (int) auth()->id()) {
            abort(403);
        }

        return (int) auth()->id();
    }

    private function summary(
        Builder $studentCompanies,
        Builder $registrations,
        Builder $attendances,
        Builder $fieldVisits,
        Builder $leaveRequests,
        Builder $studentReports
    ): array {
        return [
            'supervised_students_count' => (clone $studentCompanies)
                ->distinct('student_id')
                ->count('student_id'),
            'student_companies_count' => (clone $studentCompanies)->count(),
            'companies_count' => (clone $studentCompanies)
                ->whereNotNull('company_id')
                ->distinct('company_id')
                ->count('company_id'),
            'active_training_count' => (clone $studentCompanies)
                ->where('status', TrainingStatus::AVAILABLE->value)
                ->count(),
            'finished_training_count' => (clone $studentCompanies)
                ->where('status', TrainingStatus::FINISHED->value)
                ->count(),
            'field_visits_count' => (clone $fieldVisits)->count(),
            'attendance_records_count' => (clone $attendances)->whereNotNull('check_in')->count(),
            'attendance_days_count' => (clone $attendances)
                ->whereNotNull('check_in')
                ->distinct('attendance_date')
                ->count('attendance_date'),
            'student_reports_count' => (clone $studentReports)->count(),
            'leave_requests_count' => (clone $leaveRequests)->count(),
            'pending_leave_requests_count' => (clone $leaveRequests)
                ->where('university_approval', LeaveRequestStatus::PENDING->value)
                ->count(),
            'approved_leave_requests_count' => (clone $leaveRequests)
                ->where('university_approval', LeaveRequestStatus::APPROVED->value)
                ->count(),
            'rejected_leave_requests_count' => (clone $leaveRequests)
                ->where('university_approval', LeaveRequestStatus::REJECTED->value)
                ->count(),
            'enrolled_students_without_company_count' => $this->enrolledStudentsWithoutCompanyCount($registrations),
        ];
    }

    private function studentCompaniesQuery(?int $supervisorId, int $semester, int $year): Builder
    {
        return StudentCompany::query()
            ->whereHas('registration', function (Builder $query) use ($supervisorId, $semester, $year): void {
                $query
                    ->where('semester', $semester)
                    ->where('year', $year)
                    ->when($supervisorId, fn (Builder $query) => $query->where('supervisor_id', $supervisorId));
            });
    }

    private function registrationsQuery(?int $supervisorId, int $semester, int $year): Builder
    {
        return Registration::query()
            ->where('semester', $semester)
            ->where('year', $year)
            ->when($supervisorId, fn (Builder $query) => $query->where('supervisor_id', $supervisorId));
    }

    private function attendanceQuery(Builder $studentCompanies): Builder
    {
        return StudentAttendance::query()
            ->whereIn('student_company_id', $this->studentCompanyIds($studentCompanies));
    }

    private function fieldVisitsQuery(Builder $studentCompanies, ?int $supervisorId): Builder
    {
        return FieldVisit::query()
            ->whereIn('student_company_id', $this->studentCompanyIds($studentCompanies))
            ->when($supervisorId, fn (Builder $query) => $query->where('supervisor_id', $supervisorId));
    }

    private function leaveRequestsQuery(Builder $studentCompanies): Builder
    {
        return LeaveRequest::query()
            ->whereIn('student_company_id', $this->studentCompanyIds($studentCompanies));
    }

    private function studentReportsQuery(Builder $studentCompanies): Builder
    {
        return StudentReport::query()
            ->whereHas('studentAttendance', function (Builder $query) use ($studentCompanies): void {
                $query->whereIn('student_company_id', $this->studentCompanyIds($studentCompanies));
            });
    }

    private function studentCompanyIds(Builder $studentCompanies): Builder
    {
        $table = (new StudentCompany)->getTable();

        return (clone $studentCompanies)
            ->select("{$table}.id")
            ->reorder();
    }

    private function applyDateRange(Builder $query, string $column, ?string $dateFrom, ?string $dateTo): Builder
    {
        return $query
            ->when($dateFrom, fn (Builder $query) => $query->whereDate($column, '>=', $dateFrom))
            ->when($dateTo, fn (Builder $query) => $query->whereDate($column, '<=', $dateTo));
    }

    private function enumStatistics(array $cases, Builder $query, string $column): array
    {
        $items = collect($cases)
            ->map(fn ($case): array => [
                'value' => $case->value,
                'label' => $case->getLabel(),
                'count' => (clone $query)->where($column, $case->value)->count(),
            ])
            ->values();

        return [
            'labels' => $items->pluck('label')->all(),
            'data' => $items->pluck('count')->all(),
            'items' => $items->all(),
        ];
    }

    private function attendanceLastSevenDays(Builder $attendances): array
    {
        $items = collect($this->lastDays(7))
            ->map(fn (Carbon $date): array => [
                'date' => $date->toDateString(),
                'label' => $date->translatedFormat('d M'),
                'count' => (clone $attendances)
                    ->whereDate('attendance_date', $date->toDateString())
                    ->whereNotNull('check_in')
                    ->count(),
            ])
            ->values();

        return [
            'labels' => $items->pluck('label')->all(),
            'data' => $items->pluck('count')->all(),
            'items' => $items->all(),
        ];
    }

    private function fieldVisitsLastSixMonths(Builder $fieldVisits): array
    {
        $items = collect($this->monthStarts(6))
            ->map(fn (Carbon $month): array => [
                'month' => $month->format('Y-m'),
                'label' => $month->translatedFormat('M Y'),
                'count' => (clone $fieldVisits)
                    ->whereDate('visit_date', '>=', $month->toDateString())
                    ->whereDate('visit_date', '<=', $month->copy()->endOfMonth()->toDateString())
                    ->count(),
            ])
            ->values();

        return [
            'labels' => $items->pluck('label')->all(),
            'data' => $items->pluck('count')->all(),
            'items' => $items->all(),
        ];
    }

    private function leaveRequestStatusStatistics(Builder $leaveRequests): array
    {
        $items = collect(LeaveRequestStatus::cases())
            ->map(fn (LeaveRequestStatus $status): array => [
                'value' => $status->value,
                'label' => $status->getLabel(),
                'company_approval_count' => (clone $leaveRequests)
                    ->where('company_approval', $status->value)
                    ->count(),
                'university_approval_count' => (clone $leaveRequests)
                    ->where('university_approval', $status->value)
                    ->count(),
            ])
            ->values();

        return [
            'labels' => $items->pluck('label')->all(),
            'company_approval' => $items->pluck('company_approval_count')->all(),
            'university_approval' => $items->pluck('university_approval_count')->all(),
            'items' => $items->all(),
        ];
    }

    private function studentsByGender(Builder $registrations): array
    {
        $items = collect(StudentGender::cases())
            ->map(fn (StudentGender $gender): array => [
                'value' => $gender->value,
                'label' => $gender->getLabel(),
                'count' => (clone $registrations)
                    ->whereHas('student.studentProfile', fn (Builder $query) => $query->where('gender', $gender->value))
                    ->distinct('student_id')
                    ->count('student_id'),
            ])
            ->values();

        return [
            'labels' => $items->pluck('label')->all(),
            'data' => $items->pluck('count')->all(),
            'items' => $items->all(),
        ];
    }

    private function studentsByMajor(Builder $registrations): array
    {
        $items = Major::query()
            ->orderBy('id')
            ->get()
            ->map(fn (Major $major): array => [
                'value' => $major->id,
                'label' => $major->name ?: __('No Program'),
                'count' => (clone $registrations)
                    ->whereHas('student.studentProfile', fn (Builder $query) => $query->where('major_id', $major->id))
                    ->distinct('student_id')
                    ->count('student_id'),
            ])
            ->filter(fn (array $item): bool => $item['count'] > 0)
            ->values();

        $withoutMajor = (clone $registrations)
            ->where(function (Builder $query): void {
                $query
                    ->whereDoesntHave('student.studentProfile')
                    ->orWhereHas('student.studentProfile', fn (Builder $query) => $query->whereNull('major_id'));
            })
            ->distinct('student_id')
            ->count('student_id');

        if ($withoutMajor > 0) {
            $items->push([
                'value' => null,
                'label' => __('No Program'),
                'count' => $withoutMajor,
            ]);
        }

        return [
            'labels' => $items->pluck('label')->all(),
            'data' => $items->pluck('count')->all(),
            'items' => $items->all(),
        ];
    }

    private function enrolledStudentsWithoutCompanyCount(Builder $registrations): int
    {
        return (clone $registrations)
            ->whereNotIn(
                'id',
                StudentCompany::query()
                    ->whereNotNull('company_id')
                    ->select('registration_id')
            )
            ->count();
    }

    private function lastDays(int $days): array
    {
        return collect(range($days - 1, 0))
            ->map(fn (int $daysAgo) => now()->subDays($daysAgo)->startOfDay())
            ->all();
    }

    private function monthStarts(int $months): array
    {
        return collect(range($months - 1, 0))
            ->map(fn (int $monthsAgo) => now()->subMonthsNoOverflow($monthsAgo)->startOfMonth())
            ->all();
    }
}
