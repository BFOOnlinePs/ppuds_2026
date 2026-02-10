<?php

namespace Modules\PPUDS\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Core\Traits\ApiResponse;
use Modules\PPUDS\Entities\StudentAttendance;
use Modules\PPUDS\Http\Requests\StudentAttendanceRequest;
use Modules\PPUDS\Transformers\V1\StudentAttendanceResource;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @OA\Tag(
 * name="Student Attendances",
 * description="API Endpoints for managing student check-ins and check-outs"
 * )
 */
class StudentAttendanceController extends Controller
{
    use ApiResponse;

    /**
     *List Student Attendances
     *
     * Retrieve a paginated list of student attendances with filtering and sorting options.
     *
     * @OA\Get(
     * path="/api/v1/ppuds/attendances",
     * summary="List all attendances",
     * tags={"Student Attendances"},
     * security={{"sanctum": {}}},
     * @OA\Parameter(
     * name="filter[student_company_id]",
     * in="query",
     * description="Filter by Student Company ID",
     * required=false,
     * @OA\Schema(type="integer", example=10)
     * ),
     * @OA\Parameter(
     * name="filter[attendance_date]",
     * in="query",
     * description="Filter by specific date (YYYY-MM-DD)",
     * required=false,
     * @OA\Schema(type="string", format="date", example="2024-03-15")
     * ),
     * @OA\Parameter(
     * name="filter[status]",
     * in="query",
     * description="Filter by status (present, absent, late, excused)",
     * required=false,
     * @OA\Schema(type="string", example="present")
     * ),
     * @OA\Parameter(
     * name="sort",
     * in="query",
     * description="Sort results (e.g. -attendance_date, created_at)",
     * required=false,
     * @OA\Schema(type="string", example="-attendance_date")
     * ),
     * @OA\Parameter(
     * name="per_page",
     * in="query",
     * description="Number of items per page",
     * required=false,
     * @OA\Schema(type="integer", example=15)
     * ),
     * @OA\Parameter(
     * name="page",
     * in="query",
     * description="Page number",
     * required=false,
     * @OA\Schema(type="integer", example=1)
     * ),
     * @OA\Response(
     * response=200,
     * description="Successful retrieval",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Attendances retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="array",
     * @OA\Items(
     * type="object",
     * @OA\Property(property="id", type="integer", example=1),
     * @OA\Property(property="student_company_id", type="integer", example=5),
     * @OA\Property(property="attendance_date", type="string", format="date", example="2024-03-15"),
     * @OA\Property(
     * property="check_in",
     * type="object",
     * @OA\Property(property="time", type="string", example="08:00:00"),
     * @OA\Property(property="latitude", type="number", format="float", example=31.9522),
     * @OA\Property(property="longitude", type="number", format="float", example=35.2332)
     * ),
     * @OA\Property(property="status", type="string", example="present")
     * )
     * ),
     * @OA\Property(
     * property="meta",
     * type="object",
     * description="Pagination metadata"
     * )
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index()
    {
        $attendances = QueryBuilder::for(StudentAttendance::class)
            ->allowedFilters(StudentAttendanceResource::allowedFilters())
            ->allowedSorts(StudentAttendanceResource::allowedSorts())
            ->allowedIncludes(StudentAttendanceResource::allowedIncludes())
            ->defaultSort('-attendance_date')
            ->paginate(request('per_page', 15));

        return $this->successResponse(
            StudentAttendanceResource::collection($attendances),
            __('Attendances retrieved successfully')
        );
    }

    /**
     * Student Check-In
     *
     * Record a student's arrival at the company location.
     *
     * @OA\Post(
     * path="/api/v1/ppuds/attendances/check-in",
     * summary="Student Check-In",
     * tags={"Student Attendances"},
     * security={{"sanctum": {}}},
     * @OA\RequestBody(
     * required=true,
     * description="Check-in data including location",
     * @OA\JsonContent(
     * required={"student_company_id", "latitude", "longitude"},
     * @OA\Property(property="student_company_id", type="integer", example=10, description="The ID of the student company record"),
     * @OA\Property(property="latitude", type="number", format="float", example=31.9038, description="Current device latitude"),
     * @OA\Property(property="longitude", type="number", format="float", example=35.2034, description="Current device longitude"),
     * @OA\Property(property="description", type="string", example="Arrived on time", description="Optional notes")
     * )
     * ),
     * @OA\Response(
     * response=201,
     * description="Check-in successful",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Checked in successfully"),
     * @OA\Property(property="data", type="object",
     * @OA\Property(property="id", type="integer", example=50),
     * @OA\Property(property="check_in_time", type="string", example="08:05:00")
     * )
     * )
     * ),
     * @OA\Response(
     * response=422,
     * description="Validation Error or Already Checked In",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="You have already checked in today.")
     * )
     * )
     * )
     */
    public function checkIn(StudentAttendanceRequest $request)
    {
        $request->validate([
            'student_company_id' => 'required|exists:student_companies,id',
            'latitude'           => 'required|numeric|between:-90,90',
            'longitude'          => 'required|numeric|between:-180,180',
            'description'        => 'nullable|string|max:1000',
        ]);

        $existing = StudentAttendance::where('student_company_id', $request->student_company_id)
            ->where('attendance_date', now()->toDateString())
            ->first();

        if ($existing) {
            return $this->errorResponse(__('You have already checked in today.'), 422);
        }

        $attendance = StudentAttendance::create([
            'student_company_id'  => $request->student_company_id,
            'attendance_date'     => now()->toDateString(),
            'check_in'            => now(),
            'check_in_latitude'   => $request->latitude,
            'check_in_longitude'  => $request->longitude,
            'status'              => 'present',
            'description'         => $request->description,
            'created_by'          => auth()->id(),
        ]);

        return $this->successResponse(
            new StudentAttendanceResource($attendance),
            __('Checked in successfully'),
            201
        );
    }

    /**
     * Student Check-Out
     *
     * Record a student's departure. Must have an existing check-in for today.
     *
     * @OA\Post(
     * path="/api/v1/ppuds/attendances/check-out",
     * summary="Student Check-Out",
     * tags={"Student Attendances"},
     * security={{"sanctum": {}}},
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"student_company_id", "latitude", "longitude"},
     * @OA\Property(property="student_company_id", type="integer", example=10),
     * @OA\Property(property="latitude", type="number", format="float", example=31.9038),
     * @OA\Property(property="longitude", type="number", format="float", example=35.2034)
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Check-out successful",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Checked out successfully")
     * )
     * ),
     * @OA\Response(
     * response=404,
     * description="No active check-in found",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="No active check-in found for today.")
     * )
     * )
     * )
     */
    public function checkOut(StudentAttendanceRequest $request)
    {
        $attendance = StudentAttendance::where('student_company_id', $request->student_company_id)
            ->where('attendance_date', now()->toDateString())
            ->whereNull('check_out')
            ->first();

        if (! $attendance) {
            return $this->errorResponse(__('No active check-in found for today.'), 404);
        }

        $attendance->update([
            'check_out'           => now(),
            'check_out_latitude'  => $request->latitude,
            'check_out_longitude' => $request->longitude,
        ]);

        return $this->successResponse(
            new StudentAttendanceResource($attendance),
            __('Checked out successfully')
        );
    }
}
