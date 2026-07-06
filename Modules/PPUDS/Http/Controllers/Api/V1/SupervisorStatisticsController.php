<?php

namespace Modules\PPUDS\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Branch\Entities\Branch;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\Core\Traits\ApiResponse;
use Modules\PPUDS\Entities\Company;
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
        return $this->statisticsResponse($request);
    }

    public function show(SupervisorStatisticsRequest $request, User $supervisor)
    {
        return $this->statisticsResponse($request, (int) $supervisor->id);
    }

    private function statisticsResponse(SupervisorStatisticsRequest $request, ?int $supervisorId = null)
    {
        $settings = app(GeneralSettings::class);
        $context = $this->supervisorContext($request, $supervisorId);
        $resolvedSupervisorId = $context['supervisor_id'];
        $semester = (int) $request->input('filter.semester', $settings->semester_type->value);
        $year = (int) $request->input('filter.year', $settings->year);
        $dateFrom = $request->input('filter.date_from');
        $dateTo = $request->input('filter.date_to');

        $studentCompanies = $this->studentCompaniesQuery($context, $semester, $year);
        $registrations = $this->registrationsQuery($context, $semester, $year);
        $attendances = $this->applyDateRange(
            $this->attendanceQuery($studentCompanies),
            'attendance_date',
            $dateFrom,
            $dateTo
        );
        $fieldVisits = $this->applyDateRange(
            $this->fieldVisitsQuery($studentCompanies, $context),
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

        $summary = $this->summary(
            $studentCompanies,
            $registrations,
            $attendances,
            $fieldVisits,
            $leaveRequests,
            $studentReports
        );
        $charts = [
            'training_statuses' => $this->enumStatistics(TrainingStatus::cases(), $studentCompanies, 'status'),
            'attendance_statuses' => $this->enumStatistics(AttendanceStatus::cases(), $attendances, 'status'),
            'attendance_last_seven_days' => $this->attendanceLastSevenDays($attendances),
            'field_visits_last_six_months' => $this->fieldVisitsLastSixMonths($fieldVisits),
            'leave_requests_statuses' => $this->leaveRequestStatusStatistics($leaveRequests),
            'students_by_gender' => $this->studentsByGender($registrations),
            'students_by_major' => $this->studentsByMajor($registrations),
        ];

        return $this->successResponse([
            'supervisor' => $this->supervisorMeta($context),
            'supervisor_id' => $resolvedSupervisorId,
            'filters' => [
                'semester' => $semester,
                'semester_label' => SemesterType::tryFrom($semester)?->getLabel(),
                'year' => $year,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'overview' => $this->overviewSection($summary),
            'students' => $this->studentsSection($summary, $registrations, $studentCompanies, $charts),
            'companies' => $this->companiesSection($summary, $studentCompanies),
            'attendance' => $this->attendanceSection($summary, $attendances, $charts),
            'field_visits' => $this->fieldVisitsSection($summary, $fieldVisits, $studentCompanies, $charts),
            'leave_requests' => $this->leaveRequestsSection($summary, $leaveRequests, $charts),
            'reports' => $this->reportsSection($summary, $studentReports),
            'sections' => $this->orderedSections(),
            'summary' => $summary,
            'charts' => $charts,
        ], __('Supervisor statistics retrieved successfully'));
    }

    private function supervisorContext(SupervisorStatisticsRequest $request, ?int $supervisorId = null): array
    {
        $requestedSupervisorId = $supervisorId ?? $request->input('filter.supervisor_id');

        if ($this->currentUserIsAdmin()) {
            return [
                'scope' => filled($requestedSupervisorId) ? $this->requestedSupervisorScope((int) $requestedSupervisorId) : 'all',
                'supervisor_id' => filled($requestedSupervisorId) ? (int) $requestedSupervisorId : null,
            ];
        }

        if ($this->shouldScopeUniversitySupervisorStudentCompanies()) {
            if (filled($requestedSupervisorId) && (int) $requestedSupervisorId !== (int) auth()->id()) {
                abort(403);
            }

            return [
                'scope' => 'university_supervisor',
                'supervisor_id' => (int) auth()->id(),
            ];
        }

        if ($this->shouldScopeCompanySupervisorStudentCompanies()) {
            if (filled($requestedSupervisorId) && (int) $requestedSupervisorId !== (int) auth()->id()) {
                abort(403);
            }

            return [
                'scope' => 'company_supervisor',
                'supervisor_id' => (int) auth()->id(),
            ];
        }

        abort(403);
    }

    private function requestedSupervisorScope(int $supervisorId): string
    {
        $supervisor = User::find($supervisorId);

        if (
            $supervisor?->hasRole(UserRole::COMPANY_SUPERVISOR->value)
            && ! $supervisor->hasAnyRole($this->universitySupervisorScopedRoles())
        ) {
            return 'company_supervisor';
        }

        return 'university_supervisor';
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

    private function supervisorMeta(array $context): array
    {
        $supervisor = filled($context['supervisor_id'])
            ? User::with('roles')->find($context['supervisor_id'])
            : null;

        return [
            'scope' => $context['scope'],
            'id' => $supervisor?->id,
            'name' => $supervisor?->name,
            'email' => $supervisor?->email,
            'phone' => $supervisor?->phone,
            'roles' => $supervisor?->roles?->pluck('name')->values() ?? [],
        ];
    }

    private function overviewSection(array $summary): array
    {
        return [
            'cards' => [
                $this->statCard(1, 'supervised_students', __('Supervised Students'), $summary['supervised_students_count'], 'primary'),
                $this->statCard(2, 'companies', __('Companies'), $summary['companies_count'], 'info'),
                $this->statCard(3, 'active_training', __('Active Training'), $summary['active_training_count'], 'success'),
                $this->statCard(4, 'field_visits', __('Field Visits'), $summary['field_visits_count'], 'warning'),
                $this->statCard(5, 'pending_leave_requests', __('Pending Leave Requests'), $summary['pending_leave_requests_count'], 'danger'),
                $this->statCard(6, 'student_reports', __('Student Reports'), $summary['student_reports_count'], 'gray'),
            ],
            'totals' => $summary,
        ];
    }

    private function studentsSection(array $summary, Builder $registrations, Builder $studentCompanies, array $charts): array
    {
        $needsFollowUp = $this->studentsNeedingFollowUpQuery($studentCompanies);

        return [
            'total' => $summary['supervised_students_count'],
            'registrations_count' => (clone $registrations)->count(),
            'without_company_count' => $summary['enrolled_students_without_company_count'],
            'active_training_count' => $summary['active_training_count'],
            'finished_training_count' => $summary['finished_training_count'],
            'needs_follow_up_count' => (clone $needsFollowUp)->count(),
            'needs_follow_up' => $this->studentsNeedingFollowUp($studentCompanies),
            'by_gender' => $charts['students_by_gender'],
            'by_major' => $charts['students_by_major'],
            'training_statuses' => $charts['training_statuses'],
        ];
    }

    private function companiesSection(array $summary, Builder $studentCompanies): array
    {
        return [
            'total' => $summary['companies_count'],
            'student_companies_count' => $summary['student_companies_count'],
            'top_companies_by_students' => $this->topCompaniesByStudents($studentCompanies),
            'top_branches_by_students' => $this->topBranchesByStudents($studentCompanies),
        ];
    }

    private function attendanceSection(array $summary, Builder $attendances, array $charts): array
    {
        $checkedInCount = (clone $attendances)->whereNotNull('check_in')->count();
        $checkedOutCount = (clone $attendances)->whereNotNull('check_out')->count();
        $approvedCount = (clone $attendances)->where('status', AttendanceStatus::APPROVED->value)->count();
        $discrepancyCount = (clone $attendances)->where('status', AttendanceStatus::DISCREPANCY->value)->count();
        $undeterminedCount = (clone $attendances)->where('status', AttendanceStatus::UNDETERMINED->value)->count();

        return [
            'records_count' => $summary['attendance_records_count'],
            'days_count' => $summary['attendance_days_count'],
            'checked_in_count' => $checkedInCount,
            'checked_out_count' => $checkedOutCount,
            'open_check_ins_count' => (clone $attendances)->whereNotNull('check_in')->whereNull('check_out')->count(),
            'approved_count' => $approvedCount,
            'discrepancy_count' => $discrepancyCount,
            'undetermined_count' => $undeterminedCount,
            'approved_percentage' => $this->percentage($approvedCount, max($checkedInCount, 1)),
            'statuses' => $charts['attendance_statuses'],
            'last_seven_days' => $charts['attendance_last_seven_days'],
        ];
    }

    private function fieldVisitsSection(array $summary, Builder $fieldVisits, Builder $studentCompanies, array $charts): array
    {
        return [
            'total' => $summary['field_visits_count'],
            'visited_students_count' => (clone $fieldVisits)
                ->whereNotNull('student_company_id')
                ->distinct('student_company_id')
                ->count('student_company_id'),
            'students_without_visits_count' => (clone $studentCompanies)
                ->whereDoesntHave('fieldVisits')
                ->count(),
            'last_six_months' => $charts['field_visits_last_six_months'],
            'recent' => $this->recentFieldVisits($fieldVisits),
        ];
    }

    private function leaveRequestsSection(array $summary, Builder $leaveRequests, array $charts): array
    {
        return [
            'total' => $summary['leave_requests_count'],
            'pending_count' => $summary['pending_leave_requests_count'],
            'approved_count' => $summary['approved_leave_requests_count'],
            'rejected_count' => $summary['rejected_leave_requests_count'],
            'statuses' => $charts['leave_requests_statuses'],
            'recent' => $this->recentLeaveRequests($leaveRequests),
        ];
    }

    private function reportsSection(array $summary, Builder $studentReports): array
    {
        return [
            'total' => $summary['student_reports_count'],
            'today_count' => (clone $studentReports)->whereDate('created_at', now()->toDateString())->count(),
            'recent' => $this->recentStudentReports($studentReports),
        ];
    }

    private function orderedSections(): array
    {
        return [
            ['order' => 1, 'key' => 'overview', 'label' => __('Overview')],
            ['order' => 2, 'key' => 'students', 'label' => __('Students')],
            ['order' => 3, 'key' => 'companies', 'label' => __('Companies')],
            ['order' => 4, 'key' => 'attendance', 'label' => __('Attendance')],
            ['order' => 5, 'key' => 'field_visits', 'label' => __('Field Visits')],
            ['order' => 6, 'key' => 'leave_requests', 'label' => __('Leave Requests')],
            ['order' => 7, 'key' => 'reports', 'label' => __('Reports')],
        ];
    }

    private function statCard(int $order, string $key, string $label, int|float $value, string $color): array
    {
        return [
            'order' => $order,
            'key' => $key,
            'label' => $label,
            'value' => $value,
            'color' => $color,
        ];
    }

    private function percentage(int|float $value, int|float $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        return round(($value / $total) * 100, 2);
    }

    private function studentCompaniesQuery(array $context, int $semester, int $year): Builder
    {
        $query = StudentCompany::query()
            ->whereHas('registration', function (Builder $query) use ($semester, $year): void {
                $query
                    ->where('semester', $semester)
                    ->where('year', $year);
            });

        if ($context['scope'] === 'university_supervisor' && $context['supervisor_id']) {
            $query->whereHas(
                'registration',
                fn (Builder $query) => $query->where('supervisor_id', $context['supervisor_id'])
            );
        }

        if ($context['scope'] === 'company_supervisor') {
            $this->applyStudentCompanySupervisorExistsScope($query, (int) $context['supervisor_id']);
        }

        return $query;
    }

    private function registrationsQuery(array $context, int $semester, int $year): Builder
    {
        $query = Registration::query()
            ->where('semester', $semester)
            ->where('year', $year);

        if ($context['scope'] === 'university_supervisor' && $context['supervisor_id']) {
            $query->where('supervisor_id', $context['supervisor_id']);
        }

        if ($context['scope'] === 'company_supervisor') {
            $query->whereIn(
                'id',
                $this->studentCompanyRegistrationIds($this->studentCompaniesQuery($context, $semester, $year))
            );
        }

        return $query;
    }

    private function attendanceQuery(Builder $studentCompanies): Builder
    {
        return StudentAttendance::query()
            ->whereIn('student_company_id', $this->studentCompanyIds($studentCompanies));
    }

    private function fieldVisitsQuery(Builder $studentCompanies, array $context): Builder
    {
        return FieldVisit::query()
            ->whereIn('student_company_id', $this->studentCompanyIds($studentCompanies))
            ->when(
                $context['scope'] === 'university_supervisor' && $context['supervisor_id'],
                fn (Builder $query) => $query->where('supervisor_id', $context['supervisor_id'])
            );
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

    private function studentCompanyRegistrationIds(Builder $studentCompanies): Builder
    {
        $table = (new StudentCompany)->getTable();

        return (clone $studentCompanies)
            ->select("{$table}.registration_id")
            ->whereNotNull("{$table}.registration_id")
            ->reorder();
    }

    private function applyDateRange(Builder $query, string $column, ?string $dateFrom, ?string $dateTo): Builder
    {
        return $query
            ->when($dateFrom, fn (Builder $query) => $query->whereDate($column, '>=', $dateFrom))
            ->when($dateTo, fn (Builder $query) => $query->whereDate($column, '<=', $dateTo));
    }

    private function topCompaniesByStudents(Builder $studentCompanies, int $limit = 5): array
    {
        $rows = (clone $studentCompanies)
            ->select('company_id')
            ->selectRaw('COUNT(*) as students_count')
            ->whereNotNull('company_id')
            ->groupBy('company_id')
            ->orderByDesc('students_count')
            ->limit($limit)
            ->get();

        $companies = Company::with('translations')
            ->whereIn('id', $rows->pluck('company_id')->filter()->all())
            ->get()
            ->keyBy('id');

        return $rows
            ->map(fn ($row): array => [
                'company_id' => (int) $row->company_id,
                'company_name' => $companies->get($row->company_id)?->name,
                'students_count' => (int) $row->students_count,
            ])
            ->values()
            ->all();
    }

    private function topBranchesByStudents(Builder $studentCompanies, int $limit = 5): array
    {
        $rows = (clone $studentCompanies)
            ->select('branch_id')
            ->selectRaw('COUNT(*) as students_count')
            ->whereNotNull('branch_id')
            ->groupBy('branch_id')
            ->orderByDesc('students_count')
            ->limit($limit)
            ->get();

        $branches = Branch::with('translations')
            ->whereIn('id', $rows->pluck('branch_id')->filter()->all())
            ->get()
            ->keyBy('id');

        return $rows
            ->map(fn ($row): array => [
                'branch_id' => (int) $row->branch_id,
                'branch_name' => $branches->get($row->branch_id)?->name,
                'students_count' => (int) $row->students_count,
            ])
            ->values()
            ->all();
    }

    private function studentsNeedingFollowUpQuery(Builder $studentCompanies): Builder
    {
        $recentDate = now()->subDays(7)->toDateString();

        return (clone $studentCompanies)
            ->where(function (Builder $query) use ($recentDate): void {
                $query
                    ->whereDoesntHave('fieldVisits')
                    ->orWhereDoesntHave('attendances', function (Builder $query) use ($recentDate): void {
                        $query
                            ->whereDate('attendance_date', '>=', $recentDate)
                            ->whereNotNull('check_in');
                    })
                    ->orWhereHas('leaveRequests', function (Builder $query): void {
                        $query->where('university_approval', LeaveRequestStatus::PENDING->value);
                    });
            });
    }

    private function studentsNeedingFollowUp(Builder $studentCompanies, int $limit = 5): array
    {
        $recentDate = now()->subDays(7)->toDateString();

        return $this->studentsNeedingFollowUpQuery($studentCompanies)
            ->with([
                'student.studentProfile',
                'company.translations',
                'branch.translations',
                'department.translations',
            ])
            ->withCount([
                'fieldVisits',
                'attendances as recent_attendance_count' => function (Builder $query) use ($recentDate): void {
                    $query
                        ->whereDate('attendance_date', '>=', $recentDate)
                        ->whereNotNull('check_in');
                },
                'leaveRequests as pending_leave_requests_count' => function (Builder $query): void {
                    $query->where('university_approval', LeaveRequestStatus::PENDING->value);
                },
            ])
            ->orderByDesc('pending_leave_requests_count')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn (StudentCompany $studentCompany): array => [
                'student_company_id' => $studentCompany->id,
                'student' => [
                    'id' => $studentCompany->student?->id,
                    'name' => $studentCompany->student?->name,
                    'student_number' => $studentCompany->student?->studentProfile?->student_number,
                ],
                'company' => [
                    'id' => $studentCompany->company?->id,
                    'name' => $studentCompany->company?->name,
                ],
                'branch' => [
                    'id' => $studentCompany->branch?->id,
                    'name' => $studentCompany->branch?->name,
                ],
                'department' => [
                    'id' => $studentCompany->department?->id,
                    'name' => $studentCompany->department?->name,
                ],
                'reasons' => $this->followUpReasons($studentCompany),
            ])
            ->values()
            ->all();
    }

    private function followUpReasons(StudentCompany $studentCompany): array
    {
        $reasons = [];

        if ((int) $studentCompany->field_visits_count === 0) {
            $reasons[] = 'no_field_visits';
        }

        if ((int) $studentCompany->recent_attendance_count === 0) {
            $reasons[] = 'no_recent_attendance';
        }

        if ((int) $studentCompany->pending_leave_requests_count > 0) {
            $reasons[] = 'pending_leave_requests';
        }

        return $reasons;
    }

    private function recentFieldVisits(Builder $fieldVisits, int $limit = 5): array
    {
        return (clone $fieldVisits)
            ->with(['studentCompany.student', 'studentCompany.company.translations', 'supervisor'])
            ->orderByDesc('visit_date')
            ->orderByDesc('visit_time')
            ->limit($limit)
            ->get()
            ->map(fn (FieldVisit $visit): array => [
                'id' => $visit->id,
                'visit_date' => $visit->visit_date?->toDateString(),
                'visit_time' => $visit->visit_time,
                'visit_duration' => $visit->visit_duration,
                'student' => [
                    'id' => $visit->studentCompany?->student?->id,
                    'name' => $visit->studentCompany?->student?->name,
                ],
                'company' => [
                    'id' => $visit->studentCompany?->company?->id,
                    'name' => $visit->studentCompany?->company?->name,
                ],
                'supervisor' => [
                    'id' => $visit->supervisor?->id,
                    'name' => $visit->supervisor?->name,
                ],
            ])
            ->values()
            ->all();
    }

    private function recentLeaveRequests(Builder $leaveRequests, int $limit = 5): array
    {
        return (clone $leaveRequests)
            ->with(['studentCompany.student', 'studentCompany.company.translations'])
            ->orderByDesc('start_at')
            ->limit($limit)
            ->get()
            ->map(fn (LeaveRequest $leaveRequest): array => [
                'id' => $leaveRequest->id,
                'type' => $leaveRequest->type,
                'start_at' => $leaveRequest->start_at?->toDateString(),
                'end_at' => $leaveRequest->end_at?->toDateString(),
                'company_approval' => $leaveRequest->company_approval,
                'university_approval' => $leaveRequest->university_approval,
                'student' => [
                    'id' => $leaveRequest->studentCompany?->student?->id,
                    'name' => $leaveRequest->studentCompany?->student?->name,
                ],
                'company' => [
                    'id' => $leaveRequest->studentCompany?->company?->id,
                    'name' => $leaveRequest->studentCompany?->company?->name,
                ],
            ])
            ->values()
            ->all();
    }

    private function recentStudentReports(Builder $studentReports, int $limit = 5): array
    {
        return (clone $studentReports)
            ->with(['studentAttendance.studentCompany.student', 'studentAttendance.studentCompany.company.translations'])
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (StudentReport $report): array => [
                'id' => $report->id,
                'student_attendance_id' => $report->student_attendance_id,
                'created_at' => $report->created_at?->toDateTimeString(),
                'student' => [
                    'id' => $report->studentAttendance?->studentCompany?->student?->id,
                    'name' => $report->studentAttendance?->studentCompany?->student?->name,
                ],
                'company' => [
                    'id' => $report->studentAttendance?->studentCompany?->company?->id,
                    'name' => $report->studentAttendance?->studentCompany?->company?->name,
                ],
            ])
            ->values()
            ->all();
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
