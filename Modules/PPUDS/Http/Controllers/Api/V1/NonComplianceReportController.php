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
use Spatie\QueryBuilder\QueryBuilder;

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
        $settings = app(GeneralSettings::class);

        // القيم الافتراضية للسنة والفصل تُدمج مع الفلاتر قبل قراءتها عبر QueryBuilder
        $request->merge([
            'filter' => array_merge(
                [
                    'year' => $settings->year,
                    'semester_type' => $settings->semester_type?->value,
                ],
                $request->input('filter', [])
            ),
        ]);

        [$date, $dateFrom, $dateTo] = NonComplianceReportResource::dateFilters($request);

        $nonComplianceTypesInput = $request->input('filter.non_compliance_types', $request->input('filter.non_compliance_type', []));
        $nonComplianceTypes = collect(is_array($nonComplianceTypesInput) ? $nonComplianceTypesInput : explode(',', (string) $nonComplianceTypesInput))
            ->map(fn (mixed $type): string => trim((string) $type))
            ->filter()
            ->values()
            ->all();

        $distanceMeters = (int) $request->input('filter.outside_work_range_distance_meters');
        $distanceMeters = $distanceMeters > 0 ? $distanceMeters : 200;

        // تحديد المسافة فقط دون نوع مخالفة يقصر الفلترة تلقائياً على "خارج نطاق العمل"
        if ($nonComplianceTypes === [] && filled($request->input('filter.outside_work_range_distance_meters'))) {
            $nonComplianceTypes = [NonComplianceReportService::ISSUE_OUTSIDE_WORK_RANGE];
        }

        $query = QueryBuilder::for(StudentCompany::class)
            ->with([
                'attendances',
                'branch.workingHours',
                'company',
                'leaveRequests',
                'registration',
                'student.studentProfile',
            ])
            ->tap(fn (Builder $query) => $this->applyStudentCompanyVisibilityScope($query))
            ->allowedFilters(NonComplianceReportResource::allowedFilters())
            ->getEloquentBuilder();

        $minimumLateHours = $request->input('filter.minimum_late_hours');

        if (is_numeric($minimumLateHours)) {
            $service->applyMinimumLateHoursFilter($query, $minimumLateHours, $date, $dateFrom, $dateTo);
        }

        $nonCompliantIds = $service->nonCompliantStudentCompanyIds(
            clone $query,
            $date,
            $dateFrom,
            $dateTo,
            $nonComplianceTypes,
            $distanceMeters
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
                            'search' => $request->input('filter.search') ?? $request->input('filter.student_number'),
                            'company_id' => $request->input('filter.company_id'),
                            'supervisor_id' => $request->input('filter.supervisor_id'),
                            'non_compliance_types' => $nonComplianceTypes,
                            'minimum_late_hours' => $minimumLateHours,
                            'outside_work_range_distance_meters' => $distanceMeters,
                            'date' => $date,
                            'date_from' => $dateFrom,
                            'date_to' => $dateTo,
                            'year' => $request->input('filter.year'),
                            'semester_type' => $request->input('filter.semester_type'),
                        ],
                    ],
                ]),
            __('Non compliance reports retrieved successfully')
        );
    }
}
