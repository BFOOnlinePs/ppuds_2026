<?php

namespace Modules\PPUDS\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Core\Traits\ApiResponse;
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Entities\StudentCompany; // تأكدنا من استخدام هذا الكلاس
use Modules\PPUDS\Enums\TrainingStatus;
use Modules\PPUDS\Http\Controllers\Api\V1\Concerns\EnsuresCurrentRegistration;
use Modules\PPUDS\Http\Requests\StudentCompanyRequest;
use Modules\PPUDS\Support\ScopesStudentCompanyVisibility;
use Modules\PPUDS\Transformers\V1\StudentCompanyResource;
use Spatie\QueryBuilder\QueryBuilder;

class StudentCompanyController extends Controller
{
    use ApiResponse;
    use EnsuresCurrentRegistration;
    use ScopesStudentCompanyVisibility;

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/student-companies",
     * summary="Get all student companies",
     * description="Retrieve a list of all student companies",
     * tags={"Student Companies"},
     * security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     * name="Accept-Language",
     * in="header",
     * required=true,
     * description="Language header (ar or en)",
     *
     * @OA\Schema(type="string", default="ar", example="en")
     * ),
     *
     * @OA\Parameter(
     * name="include",
     * in="query",
     * required=false,
     * description="Include relations (e.g. branches)",
     *
     * @OA\Schema(type="string")
     * ),
     *
     * @OA\Response(
     * response=200,
     * description="Student companies retrieved successfully",
     *
     * @OA\JsonContent(
     * type="object",
     *
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Companies retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="array",
     *
     * @OA\Items(
     * type="object",
     *
     * @OA\Property(property="id", type="integer", example=1),
     * @OA\Property(property="name", type="string", example="New Student Tech"),
     * @OA\Property(property="description", type="string", example="Student project"),
     * @OA\Property(property="created_at", type="string", format="date-time")
     * )
     * )
     * )
     * )
     * )
     */
    public function index()
    {
        $defaultPerPage = config('core.pagination.per_page', 10);
        $maxPerPage = config('core.pagination.max_per_page', 100);
        $perPage = min(request('per_page', $defaultPerPage), $maxPerPage);

        $companies = QueryBuilder::for($this->applyStudentCompanyVisibilityScope(StudentCompany::query()))
            ->allowedFields(StudentCompanyResource::allowedFields())
            ->allowedFilters(StudentCompanyResource::allowedFilters())
            ->allowedSorts(StudentCompanyResource::allowedSorts())
            ->allowedIncludes(StudentCompanyResource::allowedIncludes())
            ->withAttendanceDays()
            ->with(['media'])
            ->paginate($perPage)
            ->appends(request()->query());

        return $this->successResponse(
            StudentCompanyResource::collection($companies),
            __('Companies retrieved successfully')
        );
    }

    /**
     * @OA\Post(
     * path="/api/v1/ppuds/student-companies",
     * summary="Create a new student company",
     * description="Creates student company, branches, and links departments with supervisors.",
     * tags={"Student Companies"},
     * security={{"sanctum": {}}},
     *
     * @OA\RequestBody(
     * required=true,
     *
     * @OA\MediaType(
     * mediaType="multipart/form-data",
     *
     * @OA\Schema(
     * required={"name", "company_category_id", "status", "branches"},
     *
     * @OA\Property(property="name", type="string", example="New Tech Company"),
     * @OA\Property(property="website", type="string", example="https://example.com"),
     * @OA\Property(property="description", type="string", example="Leading tech solutions"),
     * @OA\Property(property="company_category_id", type="integer", example=1),
     * @OA\Property(property="status", type="integer", example=1),
     * @OA\Property(property="logo", type="string", format="binary"),
     * @OA\Property(
     * property="branches",
     * type="array",
     *
     * @OA\Items(
     * type="object",
     * required={"name", "country_id", "city_id", "latitude", "longitude", "opening_time", "closing_time"},
     *
     * @OA\Property(property="name", type="string", example="Main Branch"),
     * @OA\Property(property="country_id", type="integer", example=1),
     * @OA\Property(property="city_id", type="integer", example=1),
     * @OA\Property(property="latitude", type="number", example=31.90),
     * @OA\Property(property="longitude", type="number", example=35.20),
     * @OA\Property(property="opening_time", type="string", example="08:00"),
     * @OA\Property(property="closing_time", type="string", example="17:00"),
     * @OA\Property(
     * property="departments",
     * type="array",
     *
     * @OA\Items(
     * type="object",
     * required={"name", "user_id"},
     *
     * @OA\Property(property="name", type="string", example="HR"),
     * @OA\Property(property="user_id", type="integer", example=5, description="Supervisor ID")
     * )
     * )
     * )
     * )
     * )
     * )
     * ),
     *
     * @OA\Response(response=201, description="Created successfully")
     * )
     */
    public function store(StudentCompanyRequest $request)
    {
        $data = $request->validated();

        $registration = Registration::findOrFail($data['registration_id']);

        if ($response = $this->ensureRegistrationInCurrentSemester($registration)) {
            return $response;
        }

        $data['student_id'] = $registration->student_id;
        $data['created_by'] = auth()->id();

        $studentCompany = StudentCompany::onlyTrashed()
            ->where('registration_id', $data['registration_id'])
            ->where('company_id', $data['company_id'])
            ->first();

        if ($studentCompany) {
            $studentCompany->restore();
            $studentCompany->update($data);
        } else {
            $studentCompany = StudentCompany::create($data);
        }

        $studentCompany->load(['company', 'branch', 'department']);

        return $this->successResponse(
            new StudentCompanyResource($studentCompany),
            __('Student Company created successfully'),
            201
        );
    }

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/student-companies/{studentCompany}",
     * summary="Get a single student company",
     * description="Retrieve details of a specific student company",
     * tags={"Student Companies"},
     * security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     * name="studentCompany",
     * in="path",
     * required=true,
     * description="Student Company ID",
     *
     * @OA\Schema(type="integer", example=1)
     * ),
     *
     * @OA\Parameter(
     * name="Accept-Language",
     * in="header",
     * required=true,
     * description="Language header (ar or en)",
     *
     * @OA\Schema(type="string", default="ar", example="en")
     * ),
     *
     * @OA\Response(
     * response=200,
     * description="Student Company retrieved successfully",
     *
     * @OA\JsonContent(
     * type="object",
     *
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Company retrieved successfully"),
     * @OA\Property(property="data", type="object")
     * )
     * ),
     *
     * @OA\Response(response=404, description="Company not found")
     * )
     */
    public function show(StudentCompany $studentCompany)
    {
        $studentCompany = QueryBuilder::for($this->applyStudentCompanyVisibilityScope(StudentCompany::query()))
            ->where('id', $studentCompany->id)
            ->allowedFilters(StudentCompanyResource::allowedFilters())
            ->allowedFields(StudentCompanyResource::allowedFields())
            ->allowedIncludes(StudentCompanyResource::allowedIncludes())
            ->firstOrFail();

        return $this->successResponse(
            new StudentCompanyResource($studentCompany),
            __('Student Company retrieved successfully')
        );
    }

    /**
     * @OA\Delete(
     * path="/api/v1/ppuds/student-companies/{studentCompany}",
     * summary="Unlink a student from a company",
     * description="Soft deletes the student-company assignment and marks it as deleted.",
     * tags={"Student Companies"},
     * security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     * name="studentCompany",
     * in="path",
     * required=true,
     * description="Student Company ID",
     *
     * @OA\Schema(type="integer", example=1)
     * ),
     *
     * @OA\Response(
     * response=200,
     * description="Student unlinked from company successfully",
     *
     * @OA\JsonContent(
     * type="object",
     *
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Student unlinked from company successfully"),
     * @OA\Property(property="data", type="null", nullable=true, example=null)
     * )
     * ),
     *
     * @OA\Response(response=404, description="Student Company not found")
     * )
     */
    public function destroy(StudentCompany $studentCompany)
    {
        abort_unless($this->canAccessStudentCompanyRecord($studentCompany), 403);

        if ($response = $this->ensureStudentCompanyInCurrentSemester($studentCompany)) {
            return $response;
        }

        DB::transaction(function () use ($studentCompany) {
            $studentCompany->forceFill([
                'status' => TrainingStatus::DELETED,
            ])->save();

            $studentCompany->delete();
        });

        return $this->successResponse(
            null,
            __('Student unlinked from company successfully')
        );
    }
}
