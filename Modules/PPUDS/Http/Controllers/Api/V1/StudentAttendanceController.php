<?php

namespace Modules\PPUDS\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Modules\Core\Enums\UserRole;
use Modules\Core\Traits\ApiResponse;
use Modules\PPUDS\Entities\StudentAttendance;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Enums\AttendanceStatus;
use Modules\PPUDS\Http\Controllers\Api\V1\Concerns\EnsuresCurrentRegistration;
use Modules\PPUDS\Http\Requests\StudentAttendanceIndexRequest;
use Modules\PPUDS\Http\Requests\StudentAttendanceRequest;
use Modules\PPUDS\Http\Requests\StudentAttendanceRequestUpdate;
use Modules\PPUDS\Services\PpudsNotificationService;
use Modules\PPUDS\Settings\GeneralSettings;
use Modules\PPUDS\Support\ScopesStudentCompanyVisibility;
use Modules\PPUDS\Transformers\V1\StudentAttendanceResource;
use Modules\PPUDS\Transformers\V1\StudentCompanyResource;
use Spatie\QueryBuilder\AllowedFilter;
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
    use EnsuresCurrentRegistration;
    use ScopesStudentCompanyVisibility;

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
     *
     * @OA\Parameter(
     * name="filter[student_company_id]",
     * in="query",
     * description="Filter by Student Company ID",
     * required=false,
     *
     * @OA\Schema(type="integer", example=10)
     * ),
     *
     * @OA\Parameter(
     * name="filter[attendance_date]",
     * in="query",
     * description="Filter by specific date (YYYY-MM-DD)",
     * required=false,
     *
     * @OA\Schema(type="string", format="date", example="2024-03-15")
     * ),
     *
     * @OA\Parameter(
     * name="filter[attendance_date_from]",
     * in="query",
     * description="Filter by attendance date from (YYYY-MM-DD)",
     * required=false,
     *
     * @OA\Schema(type="string", format="date", example="2026-07-01")
     * ),
     *
     * @OA\Parameter(
     * name="filter[attendance_date_to]",
     * in="query",
     * description="Filter by attendance date to (YYYY-MM-DD)",
     * required=false,
     *
     * @OA\Schema(type="string", format="date", example="2026-07-31")
     * ),
     *
     * @OA\Parameter(
     * name="filter[today_present_students]",
     * in="query",
     * description="When true, return students who checked in today. If filter[attendance_date] is sent, that date is used instead of today.",
     * required=false,
     *
     * @OA\Schema(type="boolean", example=true)
     * ),
     *
     * @OA\Parameter(
     * name="filter[today_absent_students]",
     * in="query",
     * description="When true, return current semester students who did not check in today. If filter[attendance_date] is sent, that date is used instead of today.",
     * required=false,
     *
     * @OA\Schema(type="boolean", example=true)
     * ),
     *
     * @OA\Parameter(
     * name="filter[status]",
     * in="query",
     * description="Filter by attendance status enum value",
     * required=false,
     *
     * @OA\Schema(type="integer", example=1)
     * ),
     *
     * @OA\Parameter(
     * name="sort",
     * in="query",
     * description="Sort results (e.g. -attendance_date, created_at)",
     * required=false,
     *
     * @OA\Schema(type="string", example="-attendance_date")
     * ),
     *
     * @OA\Parameter(
     * name="per_page",
     * in="query",
     * description="Number of items per page",
     * required=false,
     *
     * @OA\Schema(type="integer", example=15)
     * ),
     *
     * @OA\Parameter(
     * name="page",
     * in="query",
     * description="Page number",
     * required=false,
     *
     * @OA\Schema(type="integer", example=1)
     * ),
     *
     * @OA\Response(
     * response=200,
     * description="Successful retrieval",
     *
     * @OA\JsonContent(
     * type="object",
     *
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Attendances retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="array",
     *
     * @OA\Items(
     * type="object",
     *
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
     *
     * @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(StudentAttendanceIndexRequest $request)
    {
        if ($request->boolean('filter.today_absent_students')) {
            return $this->todayAbsentStudentsResponse($request);
        }

        $perPage = min((int) $request->input('per_page', 15), config('core.pagination.max_per_page', 100));

        $attendances = QueryBuilder::for(StudentAttendance::query()
            ->whereHas(
                'studentCompany',
                fn (Builder $studentCompanyQuery): Builder => $this->applyStudentCompanyVisibilityScope($studentCompanyQuery)
            ))
            ->allowedFilters(StudentAttendanceResource::allowedFilters())
            ->allowedSorts(StudentAttendanceResource::allowedSorts())
            ->allowedIncludes(StudentAttendanceResource::allowedIncludes())
            ->defaultSort('-attendance_date')
            ->paginate($perPage)
            ->appends($request->query());

        return $this->successResponse(
            StudentAttendanceResource::collection($attendances),
            __('Attendances retrieved successfully')
        );
    }

    private function todayAbsentStudentsResponse(StudentAttendanceIndexRequest $request)
    {
        $perPage = min((int) $request->input('per_page', 15), config('core.pagination.max_per_page', 100));
        $date = $this->attendancePresenceFilterDate($request);
        $settings = app(GeneralSettings::class);
        $semester = $request->input('filter.semester', $settings->semester_type?->value);
        $year = $request->input('filter.year', $settings->year);

        $studentCompaniesQuery = StudentCompany::query()
            ->withAttendanceDays()
            ->with([
                'registration.course',
                'registration.supervisor',
                'student.media',
                'student.studentProfile.major',
                'company',
                'branch.workingHours',
                'department',
            ])
            ->whereHas('registration', function (Builder $query) use ($semester, $year): void {
                $query
                    ->when(filled($semester), fn (Builder $query): Builder => $query->where('semester', $semester))
                    ->when(filled($year), fn (Builder $query): Builder => $query->where('year', $year));
            })
            ->whereDoesntHave('attendances', function (Builder $query) use ($date): void {
                $query
                    ->whereDate('attendance_date', $date)
                    ->whereNotNull('check_in');
            })
            ->tap(fn (Builder $query): Builder => $this->applyStudentCompanyVisibilityScope($query));

        $students = QueryBuilder::for($studentCompaniesQuery)
            ->allowedFilters([
                ...StudentCompanyResource::allowedFilters(),
                AllowedFilter::exact('student_company_id', 'id'),
                AllowedFilter::callback('today_absent_students', fn (Builder $query): Builder => $query),
                AllowedFilter::callback('today_present_students', fn (Builder $query): Builder => $query),
                AllowedFilter::callback('attendance_date', fn (Builder $query): Builder => $query),
                AllowedFilter::callback('attendance_date_from', fn (Builder $query): Builder => $query),
                AllowedFilter::callback('attendance_date_to', fn (Builder $query): Builder => $query),
            ])
            ->allowedSorts(StudentCompanyResource::allowedSorts())
            ->allowedIncludes(StudentCompanyResource::allowedIncludes())
            ->defaultSort('id')
            ->paginate($perPage)
            ->appends($request->query());

        return $this->successResponse(
            StudentCompanyResource::collection($students),
            __('Absent students retrieved successfully')
        );
    }

    /**
     * Update Student Attendance
     *
     * Update an existing student attendance record's details.
     *
     * @OA\PATCH(
     * path="/api/v1/ppuds/attendances/{id}",
     * summary="Update Student Attendance",
     * tags={"Student Attendances"},
     * security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     * name="id",
     * in="path",
     * description="ID of the attendance record to update",
     * required=true,
     *
     * @OA\Schema(type="integer", example=1)
     * ),
     *
     * @OA\RequestBody(
     * required=true,
     * description="Data to update the attendance record",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="status", type="string", example="present", description="Update attendance status (e.g. present, absent, late, excused)"),
     * @OA\Property(property="description", type="string", example="Updated description for this attendance", description="Update notes/description")
     * )
     * ),
     *
     * @OA\Response(
     * response=200,
     * description="Attendance updated successfully",
     *
     * @OA\JsonContent(
     * type="object",
     *
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Attendance updated successfully"),
     * @OA\Property(
     * property="data",
     * type="object",
     * @OA\Property(property="id", type="integer", example=1),
     * @OA\Property(property="student_company_id", type="integer", example=5),
     * @OA\Property(property="status", type="string", example="present"),
     * @OA\Property(property="description", type="string", example="Updated description for this attendance")
     * )
     * )
     * ),
     *
     * @OA\Response(
     * response=404,
     * description="Attendance record not found",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="message", type="string", example="Record not found.")
     * )
     * ),
     *
     * @OA\Response(
     * response=422,
     * description="Validation Error",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="message", type="string", example="The given data was invalid.")
     * )
     * ),
     *
     * @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function update(StudentAttendanceRequestUpdate $request, StudentAttendance $studentAttendance)
    {
        abort_unless($this->canAccessStudentCompanyRecord($studentAttendance->studentCompany), 403);

        if ($response = $this->ensureStudentAttendanceInCurrentSemester($studentAttendance)) {
            return $response;
        }

        if ($request->filled('student_company_id')) {
            if ($response = $this->ensureStudentCompanyInCurrentSemester((int) $request->student_company_id)) {
                return $response;
            }

            abort_unless($this->canAccessStudentCompanyRecord((int) $request->student_company_id), 403);
        }

        $studentAttendance->update($request->validated());

        if ($request->has('status') && $studentAttendance->wasChanged('status') && ! auth()->user()?->hasRole(UserRole::STUDENT->value)) {
            app(PpudsNotificationService::class)->attendanceStatusUpdated($studentAttendance->refresh());
        }

        return $this->successResponse(
            new StudentAttendanceResource($studentAttendance),
            __('Attendance updated successfully')
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
     *
     * @OA\RequestBody(
     * required=true,
     * description="Check-in data including location",
     *
     * @OA\JsonContent(
     * required={"student_company_id", "latitude", "longitude"},
     *
     * @OA\Property(property="student_company_id", type="integer", example=10, description="The ID of the student company record"),
     * @OA\Property(property="latitude", type="number", format="float", example=31.9038, description="Current device latitude"),
     * @OA\Property(property="longitude", type="number", format="float", example=35.2034, description="Current device longitude"),
     * @OA\Property(property="attendance_date", type="string", format="date", example="2026-06-01", description="Optional attendance date from the mobile device"),
     * @OA\Property(property="check_in", type="string", format="date-time", example="2026-06-01 08:05:00", description="Optional check-in timestamp from the mobile device; defaults to server time"),
     * @OA\Property(property="description", type="string", example="Arrived on time", description="Optional notes")
     * )
     * ),
     *
     * @OA\Response(
     * response=201,
     * description="Check-in successful",
     *
     * @OA\JsonContent(
     * type="object",
     *
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Checked in successfully"),
     * @OA\Property(property="data", type="object",
     * @OA\Property(property="id", type="integer", example=50),
     * @OA\Property(property="check_in_time", type="string", example="08:05:00")
     * )
     * )
     * ),
     *
     * @OA\Response(
     * response=422,
     * description="Validation Error or Already Checked In",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="message", type="string", example="You have already checked in today.")
     * )
     * )
     * )
     */
    public function checkIn(StudentAttendanceRequest $request)
    {
        if ($response = $this->ensureStudentCompanyInCurrentSemester((int) $request->student_company_id)) {
            return $response;
        }

        abort_unless($this->canAccessStudentCompanyRecord((int) $request->student_company_id), 403);

        $checkInAt = $this->resolveAttendanceTimestamp($request, 'check_in');
        $attendanceDate = $this->resolveAttendanceDate($request, $checkInAt);

        //        $existing = StudentAttendance::where('student_company_id', $request->student_company_id)
        //            ->where('attendance_date', $attendanceDate)
        //            ->first();
        //
        //        if ($existing) {
        //            return $this->errorResponse(__('You have already checked in today.'), 422);
        //        }

        $attendance = StudentAttendance::create([
            'student_company_id' => $request->student_company_id,
            'attendance_date' => $attendanceDate,
            'check_in' => $checkInAt,
            'check_in_latitude' => $request->latitude,
            'check_in_longitude' => $request->longitude,
            'status' => AttendanceStatus::UNDETERMINED->value,
            'description' => $request->description,
            'created_by' => auth()->id(),
        ]);

        if (auth()->user()?->hasRole(UserRole::STUDENT->value)) {
            app(PpudsNotificationService::class)->attendanceCheckedIn($attendance);
        }

        return $this->successResponse(
            new StudentAttendanceResource($attendance),
            __('Checked in successfully'),
            201
        );
    }

    /**
     * Student Check-Out
     *
     * Record a student's departure. Must have an existing check-in for the attendance date.
     *
     * @OA\Post(
     * path="/api/v1/ppuds/attendances/check-out",
     * summary="Student Check-Out",
     * tags={"Student Attendances"},
     * security={{"sanctum": {}}},
     *
     * @OA\RequestBody(
     * required=true,
     *
     * @OA\JsonContent(
     * required={"student_company_id", "latitude", "longitude"},
     *
     * @OA\Property(property="student_company_id", type="integer", example=10),
     * @OA\Property(property="latitude", type="number", format="float", example=31.9038),
     * @OA\Property(property="longitude", type="number", format="float", example=35.2034),
     * @OA\Property(property="attendance_date", type="string", format="date", example="2026-06-01", description="Optional attendance date from the mobile device"),
     * @OA\Property(property="check_out", type="string", format="date-time", example="2026-06-01 16:10:00", description="Optional check-out timestamp from the mobile device; defaults to server time")
     * )
     * ),
     *
     * @OA\Response(
     * response=200,
     * description="Check-out successful",
     *
     * @OA\JsonContent(
     * type="object",
     *
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Checked out successfully")
     * )
     * ),
     *
     * @OA\Response(
     * response=404,
     * description="No active check-in found",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="message", type="string", example="No active check-in found for this attendance date.")
     * )
     * )
     * )
     */
    public function checkOut(StudentAttendanceRequest $request)
    {
        if ($response = $this->ensureStudentCompanyInCurrentSemester((int) $request->student_company_id)) {
            return $response;
        }

        abort_unless($this->canAccessStudentCompanyRecord((int) $request->student_company_id), 403);

        $checkOutAt = $this->resolveAttendanceTimestamp($request, 'check_out');
        $attendanceDate = $this->resolveAttendanceDate($request, $checkOutAt);

        $attendance = StudentAttendance::where('student_company_id', $request->student_company_id)
            ->where('attendance_date', $attendanceDate)
            ->whereNull('check_out')
            ->first();

        if (! $attendance) {
            return $this->errorResponse(__('No active check-in found for this attendance date.'), 404);
        }

        $attendance->update([
            'check_out' => $checkOutAt,
            'check_out_latitude' => $request->latitude,
            'check_out_longitude' => $request->longitude,
        ]);

        if (auth()->user()?->hasRole(UserRole::STUDENT->value)) {
            app(PpudsNotificationService::class)->attendanceCheckedOut($attendance->refresh());
        }

        return $this->successResponse(
            new StudentAttendanceResource($attendance),
            __('Checked out successfully')
        );
    }

    private function resolveAttendanceTimestamp(StudentAttendanceRequest $request, string $field): Carbon
    {
        return $request->filled($field)
            ? Carbon::parse($request->input($field))
            : now();
    }

    private function resolveAttendanceDate(StudentAttendanceRequest $request, Carbon $timestamp): string
    {
        return $request->filled('attendance_date')
            ? Carbon::parse($request->input('attendance_date'))->toDateString()
            : $timestamp->toDateString();
    }

    private function attendancePresenceFilterDate(StudentAttendanceIndexRequest $request): string
    {
        return $request->filled('filter.attendance_date')
            ? Carbon::parse($request->input('filter.attendance_date'))->toDateString()
            : now()->toDateString();
    }
}
