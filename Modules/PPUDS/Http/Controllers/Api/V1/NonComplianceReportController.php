<?php

namespace Modules\PPUDS\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Modules\Core\Traits\ApiResponse;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Services\NonComplianceReportService;
use Modules\PPUDS\Settings\GeneralSettings;
use Modules\PPUDS\Support\ScopesStudentCompanyVisibility;
use Modules\PPUDS\Transformers\V1\NonComplianceReportResource;

class NonComplianceReportController extends Controller
{
    use ApiResponse;
    use ScopesStudentCompanyVisibility;

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/non-compliance-reports",
     * summary="Get non-compliance report cards",
     * tags={"Reports"},
     * security={{"sanctum": {}}},
     *
     * @OA\Parameter(name="filter[search]", in="query", required=false, description="Student name or number", @OA\Schema(type="string")),
     * @OA\Parameter(name="filter[student_number]", in="query", required=false, description="Student name or number alias", @OA\Schema(type="string")),
     * @OA\Parameter(name="filter[company_id]", in="query", required=false, @OA\Schema(type="integer")),
     * @OA\Parameter(name="filter[supervisor_id]", in="query", required=false, @OA\Schema(type="integer")),
     * @OA\Parameter(name="filter[year]", in="query", required=false, description="Academic year. Defaults to the configured dashboard year.", @OA\Schema(type="integer")),
     * @OA\Parameter(name="filter[semester_type]", in="query", required=false, description="Semester type. Defaults to the configured dashboard semester.", @OA\Schema(type="string")),
     * @OA\Parameter(name="filter[non_compliance_types]", in="query", required=false, description="Comma separated values: outside_work_range, late_attendance, absence", @OA\Schema(type="string")),
     * @OA\Parameter(name="filter[non_compliance_type]", in="query", required=false, description="Single value alias for filter[non_compliance_types]", @OA\Schema(type="string")),
     * @OA\Parameter(name="filter[minimum_late_hours]", in="query", required=false, @OA\Schema(type="number", format="float", example=2)),
     * @OA\Parameter(name="filter[outside_work_range_distance_meters]", in="query", required=false, description="Allowed distance from branch in meters. If filter[non_compliance_types] is not also provided, the type filter is automatically narrowed to outside_work_range so this filter actually affects the result set.", @OA\Schema(type="integer", example=200)),
     * @OA\Parameter(name="filter[date]", in="query", required=false, description="Specific day. Defaults to today when no date filter is provided.", @OA\Schema(type="string", format="date", example="2026-07-07")),
     * @OA\Parameter(name="filter[date_from]", in="query", required=false, @OA\Schema(type="string", format="date", example="2026-07-01")),
     * @OA\Parameter(name="filter[date_to]", in="query", required=false, @OA\Schema(type="string", format="date", example="2026-07-31")),
     *
     * @OA\Response(response=200, description="Non compliance reports retrieved successfully")
     * )
     */
    public function index(Request $request)
    {
        $service = app(NonComplianceReportService::class);
        $filters = $this->resolvedFilters($request);
        $dateFilters = [$filters['date'], $filters['date_from'], $filters['date_to']];
        $query = $this->filteredBaseQuery($filters);

        $minimumLateHours = $filters['minimum_late_hours'];

        if (is_numeric($minimumLateHours)) {
            $service->applyMinimumLateHoursFilter($query, $minimumLateHours, ...$dateFilters);
        }

        $nonCompliantIds = $service->nonCompliantStudentCompanyIds(
            clone $query,
            ...[
                ...$dateFilters,
                $filters['non_compliance_types'],
                $filters['outside_work_range_distance_meters'],
            ]
        );

        $defaultPerPage = config('core.pagination.per_page', 10);
        $maxPerPage = config('core.pagination.max_per_page', 100);
        $perPage = min((int) $request->input('per_page', $defaultPerPage), $maxPerPage);

        $reports = $query
            ->whereKey($nonCompliantIds)
            ->orderBy('id')
            ->paginate($perPage)
            ->appends($request->query());

        return $this->successResponse(
            NonComplianceReportResource::collection($reports)
                ->additional([
                    'meta' => [
                        'filters' => [
                            'search' => $filters['search'],
                            'company_id' => $filters['company_id'],
                            'supervisor_id' => $filters['supervisor_id'],
                            'non_compliance_types' => $filters['non_compliance_types'],
                            'minimum_late_hours' => $filters['minimum_late_hours'],
                            'outside_work_range_distance_meters' => $filters['outside_work_range_distance_meters'],
                            'date' => $dateFilters[0],
                            'date_from' => $dateFilters[1],
                            'date_to' => $dateFilters[2],
                            'year' => $filters['year'],
                            'semester_type' => $filters['semester_type'],
                        ],
                    ],
                ]),
            __('Non compliance reports retrieved successfully')
        );
    }

    private function filteredBaseQuery(array $filters): Builder
    {
        $query = StudentCompany::query()
            ->with([
                'attendances',
                'branch.workingHours',
                'company',
                'leaveRequests',
                'registration',
                'student.studentProfile',
            ])
            ->tap(fn (Builder $query) => $this->applyStudentCompanyVisibilityScope($query));

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->whereHas(
                    'student.studentProfile',
                    fn (Builder $studentProfileQuery) => $studentProfileQuery->where('student_number', 'like', "%{$search}%")
                )->orWhereHas(
                    'student',
                    fn (Builder $studentQuery) => $studentQuery->where('name', 'like', "%{$search}%")
                );
            });
        }

        return $query
            ->when(
                filled($filters['company_id']),
                fn (Builder $query): Builder => $query->where('company_id', (int) $filters['company_id'])
            )
            ->when(
                filled($filters['supervisor_id']),
                fn (Builder $query): Builder => $query->whereHas(
                    'registration',
                    fn (Builder $registrationQuery): Builder => $registrationQuery->where('supervisor_id', (int) $filters['supervisor_id'])
                )
            )
            ->when(
                filled($filters['year']),
                fn (Builder $query): Builder => $query->whereHas(
                    'registration',
                    fn (Builder $registrationQuery): Builder => $registrationQuery->where('year', $filters['year'])
                )
            )
            ->when(
                filled($filters['semester_type']),
                fn (Builder $query): Builder => $query->whereHas(
                    'registration',
                    fn (Builder $registrationQuery): Builder => $registrationQuery->where('semester', $filters['semester_type'])
                )
            );
    }

    private function resolvedFilters(Request $request): array
    {
        $settings = app(GeneralSettings::class);
        [$date, $dateFrom, $dateTo] = NonComplianceReportResource::dateFilters($request);

        $nonComplianceTypes = $this->nonComplianceTypes($request);

        if ($nonComplianceTypes === [] && $this->filledFilterValue($request, 'outside_work_range_distance_meters') !== null) {
            $nonComplianceTypes = [NonComplianceReportService::ISSUE_OUTSIDE_WORK_RANGE];
        }

        return [
            'search' => $this->stringFilter($request, 'search')
                ?? $this->stringFilter($request, 'student_number'),
            'company_id' => $this->filterValue($request, 'company_id'),
            'supervisor_id' => $this->filterValue($request, 'supervisor_id'),
            'non_compliance_types' => $nonComplianceTypes,
            'minimum_late_hours' => $this->filterValue($request, 'minimum_late_hours'),
            'outside_work_range_distance_meters' => $this->outsideWorkRangeDistanceMeters($request),
            'date' => $date,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'year' => $this->filledFilterValue($request, 'year') ?? $settings->year,
            'semester_type' => $this->filledFilterValue($request, 'semester_type') ?? $settings->semester_type?->value,
        ];
    }

    private function filterValue(Request $request, string $key): mixed
    {
        $value = $request->input("filter.{$key}");

        return is_array($value) ? reset($value) : $value;
    }

    private function stringFilter(Request $request, string $key): ?string
    {
        $value = $this->filledFilterValue($request, $key);

        return filled($value) ? trim((string) $value) : null;
    }

    private function filledFilterValue(Request $request, string $key): mixed
    {
        $value = $this->filterValue($request, $key);

        return filled($value) ? $value : null;
    }

    private function nonComplianceTypes(Request $request): array
    {
        $value = $request->input('filter.non_compliance_types', $request->input('filter.non_compliance_type', []));
        $items = is_array($value)
            ? $value
            : explode(',', (string) $value);

        return collect($items)
            ->map(fn (mixed $item): string => trim((string) $item))
            ->filter()
            ->values()
            ->all();
    }

    private function outsideWorkRangeDistanceMeters(Request $request): int
    {
        $distance = (int) $this->filterValue($request, 'outside_work_range_distance_meters');

        return $distance > 0 ? $distance : 200;
    }
}
