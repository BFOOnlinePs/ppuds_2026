<?php

namespace Modules\PPUDS\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Modules\Core\Traits\ApiResponse;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Transformers\V1\ReportResource;
use Spatie\QueryBuilder\QueryBuilder;

class ReportController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/reports",
     * summary="Get all student company reports",
     * description="Retrieve a list of all student company attendance reports with filtering and sorting",
     * tags={"Reports"},
     * security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     * name="Accept-Language",
     * in="header",
     * required=true,
     * description="Language header (ar or en)",
     * @OA\Schema(type="string", default="ar", example="en")
     * ),
     *
     * @OA\Parameter(name="filter[student_number]", in="query", required=false, description="Filter by student name or number", @OA\Schema(type="string")),
     * @OA\Parameter(name="filter[student_gender]", in="query", required=false, description="Filter by gender (Male, Female)", @OA\Schema(type="string")),
     * @OA\Parameter(name="filter[company_id]", in="query", required=false, description="Filter by company ID", @OA\Schema(type="integer")),
     * @OA\Parameter(name="filter[year]", in="query", required=false, description="Filter by academic year", @OA\Schema(type="integer")),
     * @OA\Parameter(name="filter[semester_type]", in="query", required=false, description="Filter by semester type", @OA\Schema(type="string")),
     * @OA\Parameter(name="filter[attendance_days_from]", in="query", required=false, description="Filter attendance days from", @OA\Schema(type="integer")),
     * @OA\Parameter(name="filter[attendance_days_to]", in="query", required=false, description="Filter attendance days to", @OA\Schema(type="integer")),
     *
     * @OA\Response(
     * response=200,
     * description="Reports retrieved successfully",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Reports retrieved successfully"),
     * @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/ReportResource"))
     * )
     * )
     * )
     */
    public function index()
    {
        $defaultPerPage = config('core.pagination.per_page', 10);
        $maxPerPage = config('core.pagination.max_per_page', 100);
        $perPage = min(request('per_page', $defaultPerPage), $maxPerPage);

        $baseQuery = StudentCompany::query()
            ->withAttendanceDays()
            ->withActualWorkingHours()
            ->withTotalPaymentAmount()
            ->withAttendaceLeavesDays();

        $reports = QueryBuilder::for($baseQuery)
            ->allowedFields(ReportResource::allowedFields())
            ->allowedFilters(ReportResource::allowedFilters())
            ->allowedSorts(ReportResource::allowedSorts())
            ->allowedIncludes(ReportResource::allowedIncludes())
            ->with([
                'registration',
                'student',
                'student.studentProfile',
                'company',
                'branch',
                'branch.workingHours',
                'department',
                'attendances'
            ])
            ->paginate($perPage)
            ->appends(request()->query());

        return $this->successResponse(
            ReportResource::collection($reports),
            __('Reports retrieved successfully')
        );
    }

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/reports/{id}",
     * summary="Get single report details",
     * tags={"Reports"},
     * security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     * name="id",
     * in="path",
     * required=true,
     * description="Student Company ID",
     * @OA\Schema(type="integer")
     * ),
     *
     * @OA\Response(
     * response=200,
     * description="Report retrieved successfully",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Report retrieved successfully"),
     * @OA\Property(property="data", ref="#/components/schemas/ReportResource")
     * )
     * )
     * )
     */
    public function show($id)
    {
        $baseQuery = StudentCompany::query()
            ->withAttendanceDays()
            ->withActualWorkingHours()
            ->where('id', $id);

        $report = QueryBuilder::for($baseQuery)
            ->allowedFields(ReportResource::allowedFields())
            ->allowedIncludes(ReportResource::allowedIncludes())
            ->with([
                'registration',
                'student',
                'student.studentProfile',
                'company',
                'branch',
                'branch.workingHours',
                'department',
                'attendances'
            ])
            ->firstOrFail();

        return $this->successResponse(
            new ReportResource($report),
            __('Report retrieved successfully')
        );
    }
}
