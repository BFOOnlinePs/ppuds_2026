<?php

namespace Modules\PPUDS\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Modules\Core\Traits\ApiResponse;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Services\AbsenceReportService;
use Modules\PPUDS\Settings\GeneralSettings;
use Modules\PPUDS\Support\ScopesStudentCompanyVisibility;
use Modules\PPUDS\Transformers\V1\AbsenceReportResource;

/**
 * @OA\Tag(
 * name="Absence Reports",
 * description="API Endpoints for the student absence report"
 * )
 */
class AbsenceReportController extends Controller
{
    use ApiResponse;
    use ScopesStudentCompanyVisibility;

    /**
     * List Absence Report
     *
     * Retrieve, per student training placement, the working days required vs. attended and
     * the specific absence dates within the current (or filtered) semester. Only placements
     * with at least one absence day are returned.
     *
     * @OA\Get(
     * path="/api/v1/ppuds/absence-reports",
     * summary="List student absence report",
     * tags={"Absence Reports"},
     * security={{"sanctum": {}}},
     *
     * @OA\Parameter(name="filter[student_number]", in="query", required=false, description="Filter by student name or number", @OA\Schema(type="string")),
     * @OA\Parameter(name="filter[company_id]", in="query", required=false, description="Filter by company ID", @OA\Schema(type="integer")),
     * @OA\Parameter(name="filter[supervisor_id]", in="query", required=false, description="Filter by university supervisor ID from the student's registration", @OA\Schema(type="integer")),
     * @OA\Parameter(name="filter[year]", in="query", required=false, description="Filter by academic year", @OA\Schema(type="integer")),
     * @OA\Parameter(name="filter[semester_type]", in="query", required=false, description="Filter by semester type", @OA\Schema(type="string")),
     * @OA\Parameter(name="filter[date_from]", in="query", required=false, description="Limit the report to a date range instead of the whole semester", @OA\Schema(type="string", format="date", example="2026-08-01")),
     * @OA\Parameter(name="filter[date_to]", in="query", required=false, description="Limit the report to a date range instead of the whole semester", @OA\Schema(type="string", format="date", example="2026-08-10")),
     * @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", example=15)),
     * @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", example=1)),
     *
     * @OA\Response(
     * response=200,
     * description="Absence report retrieved successfully",
     *
     * @OA\JsonContent(
     * type="object",
     *
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Absence report retrieved successfully"),
     * @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/AbsenceReportResource"))
     * )
     * ),
     *
     * @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index()
    {
        $perPage = min((int) request('per_page', config('core.pagination.per_page', 15)), config('core.pagination.max_per_page', 100));
        $page = max((int) request('page', 1), 1);
        [$dateFrom, $dateTo] = $this->dateRangeFilter();

        $service = app(AbsenceReportService::class);

        $rows = $this->baseQuery()
            ->get()
            ->map(function (StudentCompany $studentCompany) use ($service, $dateFrom, $dateTo) {
                $studentCompany->absence_summary = $service->detailedSummary($studentCompany, $dateFrom, $dateTo);

                return $studentCompany;
            })
            ->filter(fn (StudentCompany $studentCompany) => ($studentCompany->absence_summary['total_absence_days'] ?? 0) > 0)
            ->values();

        $paginator = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return $this->successResponse(
            AbsenceReportResource::collection($paginator),
            __('Absence report retrieved successfully')
        );
    }

    private function baseQuery(): Builder
    {
        $settings = app(GeneralSettings::class);
        $year = request('filter.year', $settings->year);
        $semester = request('filter.semester_type', $settings->semester_type?->value);
        $studentNumber = request('filter.student_number');
        $companyId = request('filter.company_id');
        $supervisorId = request('filter.supervisor_id');

        return StudentCompany::query()
            ->with([
                'attendances',
                'branch',
                'branch.workingHours',
                'company.translations',
                'leaveRequests',
                'registration',
                'student.studentProfile',
            ])
            ->withActualWorkingHours()
            ->whereHas('registration', fn (Builder $query) => $query
                ->when(filled($year), fn (Builder $query) => $query->where('year', $year))
                ->when(filled($semester), fn (Builder $query) => $query->where('semester', $semester)))
            ->when(filled($studentNumber), fn (Builder $query) => $query->where(function (Builder $query) use ($studentNumber) {
                $query->whereHas('student.studentProfile', fn (Builder $q) => $q->where('student_number', 'like', "%{$studentNumber}%"))
                    ->orWhereHas('student', fn (Builder $q) => $q->where('name', 'like', "%{$studentNumber}%"));
            }))
            ->when(filled($companyId), fn (Builder $query) => $query->where('company_id', $companyId))
            ->when(filled($supervisorId), fn (Builder $query) => $query->whereHas(
                'registration',
                fn (Builder $query) => $query->where('supervisor_id', $supervisorId)
            ))
            ->tap(fn (Builder $query) => $this->applyStudentCompanyVisibilityScope($query));
    }

    private function dateRangeFilter(): array
    {
        $dateFrom = request('filter.date_from');
        $dateTo = request('filter.date_to');

        return [
            filled($dateFrom) ? Carbon::parse($dateFrom)->toDateString() : null,
            filled($dateTo) ? Carbon::parse($dateTo)->toDateString() : null,
        ];
    }
}
