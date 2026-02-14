<?php

namespace Modules\PPUDS\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Modules\Core\Traits\ApiResponse;
use Modules\PPUDS\Entities\StudentReport;
use Modules\PPUDS\Http\Requests\StudentAttendanceReportRequest;
use Modules\PPUDS\Transformers\V1\StudentAttendanceReportResource;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @OA\Tag(
 * name="Student Reports",
 * description="API Endpoints for managing student daily reports"
 * )
 */
class StudentAttendanceReportController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/reports",
     * summary="List all reports",
     * tags={"Student Reports"},
     * security={{"sanctum": {}}},
     * @OA\Parameter(name="filter[student_attendance_id]", in="query", @OA\Schema(type="integer")),
     * @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", example=15)),
     * @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", example=1)),
     * @OA\Response(
     * response=200,
     * description="Successful retrieval",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Reports retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="array",
     * @OA\Items(
     * type="object",
     * @OA\Property(property="id", type="integer", example=1),
     * @OA\Property(property="student_attendance_id", type="integer", example=10),
     * @OA\Property(property="report_text", type="string"),
     * @OA\Property(property="image", type="string", nullable=true)
     * )
     * )
     * )
     * )
     * )
     */
    public function index()
    {
        $report = QueryBuilder::for(StudentReport::class)
            ->allowedFields(StudentAttendanceReportResource::allowedFields())
            ->allowedFilters(StudentAttendanceReportResource::allowedFilters())
            ->allowedSorts(StudentAttendanceReportResource::allowedSorts())
            ->allowedIncludes(StudentAttendanceReportResource::allowedIncludes())
            ->paginate(request('per_page', 15));

        return $this->successResponse(
            StudentAttendanceReportResource::collection($report),
            __('Student Attendance Reports retrieved successfully')
        );
    }

    /**
     * @OA\Post(
     * path="/api/v1/ppuds/reports",
     * summary="Create a new report",
     * tags={"Student Reports"},
     * security={{"sanctum": {}}},
     * @OA\RequestBody(
     * required=true,
     * @OA\MediaType(
     * mediaType="multipart/form-data",
     * @OA\Schema(
     * required={"student_attendance_id"},
     * @OA\Property(property="student_attendance_id", type="integer"),
     * @OA\Property(property="report_text", type="string"),
     * @OA\Property(property="file_report", type="string", format="binary")
     * )
     * )
     * ),
     * @OA\Response(
     * response=201,
     * description="Created",
     * @OA\JsonContent(
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="data", type="object")
     * )
     * )
     * )
     */
    public function store(StudentAttendanceReportRequest $request)
    {
        $report = StudentReport::create($request->validated());

        if ($request->hasFile('file_report') && method_exists($report, 'addImage')) {
            $report->addImage($request->file('file_report'));
        }

        return $this->successResponse(
            new StudentAttendanceReportResource($report),
            __('Student Attendance Report created successfully'),
            201
        );
    }

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/reports/{report}",
     * summary="Get a single report",
     * tags={"Student Reports"},
     * security={{"sanctum": {}}},
     * @OA\Parameter(name="report", in="path", required=true, @OA\Schema(type="integer")),
     * @OA\Response(
     * response=200,
     * description="Success",
     * @OA\JsonContent(
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="data", type="object")
     * )
     * )
     * )
     */
    public function show(StudentReport $report)
    {
        $report = QueryBuilder::for(StudentReport::class)
            ->allowedFields(StudentAttendanceReportResource::allowedFields())
            ->allowedFilters(StudentAttendanceReportResource::allowedFilters())
            ->allowedSorts(StudentAttendanceReportResource::allowedSorts())
            ->allowedIncludes(StudentAttendanceReportResource::allowedIncludes())
            ->where('id', $report->id)
            ->firstOrFail();

        return $this->successResponse(
            new StudentAttendanceReportResource($report),
            __('Student Attendance Report retrieved successfully')
        );
    }
}