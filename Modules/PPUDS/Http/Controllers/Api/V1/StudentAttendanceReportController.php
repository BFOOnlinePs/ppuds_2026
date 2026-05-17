<?php

namespace Modules\PPUDS\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Modules\Core\Traits\ApiResponse;
use Modules\PPUDS\Entities\StudentReport;
use Modules\PPUDS\Http\Controllers\Api\V1\Concerns\EnsuresCurrentRegistration;
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
    use EnsuresCurrentRegistration;

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
     * Create Student Report
     *
     * Create a new daily report for a specific attendance record.
     *
     * @OA\Post(
     * path="/api/v1/ppuds/attendances/reports",
     * summary="Create a new report",
     * tags={"Student Reports"},
     * security={{"sanctum": {}}},
     *
     * @OA\RequestBody(
     * required=true,
     *
     * @OA\MediaType(
     * mediaType="multipart/form-data",
     *
     * @OA\Schema(
     * required={"student_attendance_id"},
     *
     * @OA\Property(property="student_attendance_id", type="integer", example=5, description="The ID of the related attendance record"),
     * @OA\Property(property="report_text", type="string", example="Today I learned about...", description="The content of the report"),
     * @OA\Property(property="company_feedback", type="string", example="Excellent work", description="Feedback from the company mentor"),
     * @OA\Property(property="academic_feedback", type="string", example="Keep it up", description="Feedback from the academic supervisor"),
     * @OA\Property(property="submit_latitude", type="number", format="float", example=31.9038),
     * @OA\Property(property="submit_longitude", type="number", format="float", example=35.2034),
     * @OA\Property(
     * property="file_report",
     * type="string",
     * format="binary",
     * description="Optional image/file attachment for the report"
     * )
     * )
     * )
     * ),
     *
     * @OA\Response(
     * response=201,
     * description="Report created successfully",
     *
     * @OA\JsonContent(
     * type="object",
     *
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Student Attendance Report created successfully"),
     * @OA\Property(
     * property="data",
     * ref="#/components/schemas/StudentAttendanceReportResource"
     * )
     * )
     * ),
     *
     * @OA\Response(
     * response=422,
     * description="Validation Error",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="message", type="string", example="The given data was invalid."),
     * @OA\Property(property="errors", type="object")
     * )
     * )
     * )
     */
    public function store(StudentAttendanceReportRequest $request)
    {
        $data = $request->validated();

        if ($response = $this->ensureStudentAttendanceInCurrentSemester((int) $data['student_attendance_id'])) {
            return $response;
        }

        $data['created_by'] = auth()->id();

        $report = StudentReport::create($data);

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
     * Get Student Report Details
     *
     * Retrieve details of a specific report by its ID.
     *
     * @OA\Get(
     * path="/api/v1/ppuds/attendances/reports/{report}",
     * summary="Get a single report",
     * tags={"Student Reports"},
     * security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     * name="report",
     * in="path",
     * required=true,
     * description="The ID of the report",
     *
     * @OA\Schema(type="integer", example=1)
     * ),
     *
     * @OA\Response(
     * response=200,
     * description="Report retrieved successfully",
     *
     * @OA\JsonContent(
     * type="object",
     *
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Student Attendance Report retrieved successfully"),
     * @OA\Property(
     * property="data",
     * ref="#/components/schemas/StudentAttendanceReportResource"
     * )
     * )
     * ),
     *
     * @OA\Response(
     * response=404,
     * description="Report not found",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="message", type="string", example="No query results for model [Modules\\PPUDS\\Entities\\StudentReport].")
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
