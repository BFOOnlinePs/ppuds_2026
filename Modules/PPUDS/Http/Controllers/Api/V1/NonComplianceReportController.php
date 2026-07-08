<?php

namespace Modules\PPUDS\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Modules\Core\Traits\ApiResponse;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Services\NonComplianceReportService;
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
     * @OA\Parameter(name="filter[year]", in="query", required=false, @OA\Schema(type="integer")),
     * @OA\Parameter(name="filter[semester_type]", in="query", required=false, @OA\Schema(type="string")),
     * @OA\Parameter(name="filter[non_compliance_types]", in="query", required=false, description="Comma separated values: outside_work_range, late_attendance, absence", @OA\Schema(type="string")),
     * @OA\Parameter(name="filter[minimum_late_hours]", in="query", required=false, @OA\Schema(type="number", format="float", example=2)),
     * @OA\Parameter(name="filter[outside_work_range_distance_meters]", in="query", required=false, @OA\Schema(type="integer", example=200)),
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
        $dateFilters = NonComplianceReportResource::dateFilters($request);
        $query = $this->filteredBaseQuery($request);

        $minimumLateHours = $this->filterValue($request, 'minimum_late_hours');

        if (is_numeric($minimumLateHours)) {
            $service->applyMinimumLateHoursFilter($query, $minimumLateHours, ...$dateFilters);
        }

        $nonCompliantIds = $service->nonCompliantStudentCompanyIds(
            clone $query,
            ...[
                ...$dateFilters,
                $this->nonComplianceTypes($request),
                $this->outsideWorkRangeDistanceMeters($request),
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
                            'date' => $dateFilters[0],
                            'date_from' => $dateFilters[1],
                            'date_to' => $dateFilters[2],
                        ],
                    ],
                ]),
            __('Non compliance reports retrieved successfully')
        );
    }

    private function filteredBaseQuery(Request $request): Builder
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

        $search = trim((string) ($this->filterValue($request, 'search') ?? $this->filterValue($request, 'student_number') ?? ''));

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
                filled($this->filterValue($request, 'company_id')),
                fn (Builder $query): Builder => $query->where('company_id', (int) $this->filterValue($request, 'company_id'))
            )
            ->when(
                filled($this->filterValue($request, 'supervisor_id')),
                fn (Builder $query): Builder => $query->whereHas(
                    'registration',
                    fn (Builder $registrationQuery): Builder => $registrationQuery->where('supervisor_id', (int) $this->filterValue($request, 'supervisor_id'))
                )
            )
            ->when(
                filled($this->filterValue($request, 'year')),
                fn (Builder $query): Builder => $query->whereHas(
                    'registration',
                    fn (Builder $registrationQuery): Builder => $registrationQuery->where('year', $this->filterValue($request, 'year'))
                )
            )
            ->when(
                filled($this->filterValue($request, 'semester_type')),
                fn (Builder $query): Builder => $query->whereHas(
                    'registration',
                    fn (Builder $registrationQuery): Builder => $registrationQuery->where('semester', $this->filterValue($request, 'semester_type'))
                )
            );
    }

    private function filterValue(Request $request, string $key): mixed
    {
        $value = $request->input("filter.{$key}");

        return is_array($value) ? reset($value) : $value;
    }

    private function nonComplianceTypes(Request $request): array
    {
        $value = $request->input('filter.non_compliance_types', []);
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
