<?php

namespace Modules\PPUDS\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Core\Traits\ApiResponse;
use Modules\PPUDS\Entities\FieldVisit;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Http\Controllers\Api\V1\Concerns\EnsuresCurrentRegistration;
use Modules\PPUDS\Http\Requests\BulkFieldVisitRequest;
use Modules\PPUDS\Http\Requests\FieldVisitCompanyStudentsRequest;
use Modules\PPUDS\Http\Requests\FieldVisitRequest;
use Modules\PPUDS\Http\Requests\FieldVisitUpdate;
use Modules\PPUDS\Settings\GeneralSettings;
use Modules\PPUDS\Support\ScopesStudentCompanyVisibility;
use Modules\PPUDS\Transformers\V1\FieldVisitResource;
use Modules\PPUDS\Transformers\V1\StudentCompanyResource;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @OA\Tag(
 * name="Field Visits",
 * description="API Endpoints for managing student field visits"
 * )
 */
class FieldVisitController extends Controller
{
    use ApiResponse;
    use EnsuresCurrentRegistration;
    use ScopesStudentCompanyVisibility;

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/field-visits",
     * summary="List all field visits",
     * description="Retrieve a list of field visits with filtering and sorting",
     * tags={"Field Visits"},
     * security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     * name="filter[student_company_id]",
     * in="query",
     * description="Filter by Student Company ID",
     *
     * @OA\Schema(type="integer")
     * ),
     *
     * @OA\Parameter(
     * name="filter[supervisor_id]",
     * in="query",
     * description="Filter by the student's university supervisor ID",
     *
     * @OA\Schema(type="integer")
     * ),
     *
     * @OA\Parameter(
     * name="filter[supervisor_id]",
     * in="query",
     * description="Alias for filter[supervisor_id]",
     *
     * @OA\Schema(type="integer")
     * ),
     *
     * @OA\Parameter(
     * name="filter[field_visit_supervisor_id]",
     * in="query",
     * description="Filter by the supervisor assigned on the field visit record",
     *
     * @OA\Schema(type="integer")
     * ),
     *
     * @OA\Parameter(
     * name="filter[visit_date]",
     * in="query",
     * description="Filter by Visit Date",
     *
     * @OA\Schema(type="string", format="date")
     * ),
     *
     * @OA\Response(
     * response=200,
     * description="Field visits retrieved successfully",
     *
     * @OA\JsonContent(
     * type="object",
     *
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/FieldVisitResource"))
     * )
     * )
     * )
     */
    public function index()
    {
        $defaultPerPage = config('core.pagination.per_page', 10);
        $maxPerPage = config('core.pagination.max_per_page', 100);
        $perPage = min(request('per_page', $defaultPerPage), $maxPerPage);

        $fieldVisits = QueryBuilder::for($this->visibleFieldVisitsQuery())
            ->allowedFields(FieldVisitResource::allowedFields())
            ->allowedFilters(FieldVisitResource::allowedFilters())
            ->allowedSorts(FieldVisitResource::allowedSorts())
            ->allowedIncludes(FieldVisitResource::allowedIncludes())
            ->paginate($perPage)
            ->appends(request()->query());

        return $this->successResponse(
            FieldVisitResource::collection($fieldVisits),
            __('Field Visits retrieved successfully')
        );
    }

    /**
     * @OA\Post(
     * path="/api/v1/ppuds/field-visits",
     * summary="Create a new field visit",
     * description="Submit a new field visit record",
     * tags={"Field Visits"},
     * security={{"sanctum": {}}},
     *
     * @OA\RequestBody(
     * required=true,
     *
     * @OA\MediaType(
     * mediaType="multipart/form-data",
     *
     * @OA\Schema(
     * required={"student_company_id", "supervisor_id", "visiting_place", "visit_date", "visit_time", "visit_duration"},
     *
     * @OA\Property(property="student_company_id", type="integer", example=1),
     * @OA\Property(property="supervisor_id", type="integer", example=3),
     * @OA\Property(property="visiting_place", type="string", example="Main Office"),
     * @OA\Property(property="visit_date", type="string", format="date", example="2024-05-20"),
     * @OA\Property(property="visit_time", type="string", format="time", example="09:00:00"),
     * @OA\Property(property="visit_duration", type="integer", example=60),
     * @OA\Property(property="notes", type="string", example="Everything went well"),
     * @OA\Property(property="attachment", type="string", format="binary", description="Optional single attachment"),
     * @OA\Property(property="image", type="string", format="binary", description="Optional single image attachment"),
     * @OA\Property(
     * property="attachments",
     * type="array",
     * @OA\Items(type="string", format="binary"),
     * description="Optional attachments. In Postman send as attachments[] for multiple files."
     * ),
     * @OA\Property(
     * property="images",
     * type="array",
     * @OA\Items(type="string", format="binary"),
     * description="Optional image attachments. In Postman send as images[] for multiple files."
     * )
     * )
     * )
     * ),
     *
     * @OA\Response(response=201, description="Created successfully")
     * )
     */
    public function store(FieldVisitRequest $request)
    {
        $data = $request->validated();
        $attachments = $request->attachmentFiles();
        unset($data['attachment'], $data['image'], $data['attachments'], $data['images']);

        if (! $this->canAccessStudentCompanyRecord((int) $data['student_company_id'])) {
            return $this->errorResponse(__('You are not authorized to access this student company.'), 403);
        }

        if ($response = $this->ensureStudentCompanyInCurrentSemester((int) $data['student_company_id'])) {
            return $response;
        }

        if (! $this->studentCompanyBelongsToSupervisor((int) $data['student_company_id'], (int) $data['supervisor_id'])) {
            return $this->errorResponse(__('The selected student does not belong to the selected supervisor.'), 422);
        }

        $data['created_by'] = auth()->id();

        $fieldVisit = FieldVisit::create($data);

        $this->addAttachments($fieldVisit, $attachments);

        return $this->successResponse(
            new FieldVisitResource($fieldVisit->loadMissing('media')),
            __('Field Visit created successfully'),
            201
        );
    }

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/field-visits/company-students",
     * summary="Get company students for bulk field visits",
     * description="Choose a company and return current semester students available for bulk field visit creation.",
     * tags={"Field Visits"},
     * security={{"sanctum": {}}},
     *
     * @OA\Parameter(name="filter[company_id]", in="query", required=true, @OA\Schema(type="integer", example=1)),
     * @OA\Parameter(name="filter[supervisor_id]", in="query", required=false, description="Alias for filter[supervisor_id]", @OA\Schema(type="integer", example=3)),
     * @OA\Parameter(name="filter[search]", in="query", required=false, @OA\Schema(type="string", example="Ahmad")),
     * @OA\Parameter(name="filter[visit_date]", in="query", required=false, @OA\Schema(type="string", format="date", example="2026-07-01")),
     * @OA\Parameter(name="filter[without_visit_date]", in="query", required=false, @OA\Schema(type="string", format="date", example="2026-07-01")),
     * @OA\Parameter(name="filter[visit_date_from]", in="query", required=false, @OA\Schema(type="string", format="date", example="2026-07-01")),
     * @OA\Parameter(name="filter[visit_date_to]", in="query", required=false, @OA\Schema(type="string", format="date", example="2026-07-31")),
     * @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", example=25)),
     *
     * @OA\Response(response=200, description="Company students retrieved successfully")
     * )
     */
    public function companyStudents(FieldVisitCompanyStudentsRequest $request)
    {
        $defaultPerPage = config('core.pagination.per_page', 10);
        $maxPerPage = config('core.pagination.max_per_page', 100);
        $perPage = min((int) $request->validated('per_page', $defaultPerPage), $maxPerPage);

        $students = QueryBuilder::for($this->currentSemesterStudentCompanyQuery())
            ->allowedFilters(StudentCompanyResource::allowedFilters())
            ->with([
                'student.media',
                'student.studentProfile.major',
                'registration.course',
                'registration.supervisor',
                'company',
                'branch.workingHours',
                'branch.departments.supervisors',
                'branch.supervisors',
                'department',
                'department.supervisors',
            ])
            ->orderBy('student_id')
            ->paginate($perPage)
            ->appends($request->query());

        return $this->successResponse(
            StudentCompanyResource::collection($students),
            __('Company students retrieved successfully')
        );
    }

    /**
     * @OA\Post(
     * path="/api/v1/ppuds/field-visits/bulk",
     * summary="Create field visits for selected students",
     * description="Create the same field visit for selected student company records from one company.",
     * tags={"Field Visits"},
     * security={{"sanctum": {}}},
     *
     * @OA\RequestBody(
     * required=true,
     *
     * @OA\MediaType(
     * mediaType="application/json",
     *
     * @OA\Schema(
     * required={"company_id", "student_company_ids", "supervisor_id", "visiting_place", "visit_date", "visit_time", "visit_duration"},
     *
     * @OA\Property(property="company_id", type="integer", example=1),
     * @OA\Property(property="student_company_ids", type="array", @OA\Items(type="integer"), example={10, 11, 12}),
     * @OA\Property(property="supervisor_id", type="integer", example=3),
     * @OA\Property(property="visiting_place", type="string", example="Main Office"),
     * @OA\Property(property="visit_date", type="string", format="date", example="2026-07-01"),
     * @OA\Property(property="visit_time", type="string", format="time", example="09:00:00"),
     * @OA\Property(property="visit_duration", type="integer", example=60),
     * @OA\Property(property="notes", type="string", nullable=true, example="Bulk field visit")
     * )
     * )
     * ),
     *
     * @OA\Response(response=201, description="Field visits created successfully")
     * )
     */
    public function bulkStore(BulkFieldVisitRequest $request)
    {
        $data = $request->validated();
        $studentCompanyIds = $request->studentCompanyIds();

        $studentCompanies = $this->currentSemesterStudentCompanyQuery()
            ->where('company_id', $data['company_id'])
            ->whereHas('registration', function (Builder $query) use ($data): void {
                $query->where('supervisor_id', $data['supervisor_id']);
            })
            ->whereIn('id', $studentCompanyIds)
            ->get();

        if ($studentCompanies->count() !== count($studentCompanyIds)) {
            return $this->errorResponse(
                __('Some selected students are not available for this company in the current semester.'),
                422
            );
        }

        $visitData = [
            'supervisor_id' => $data['supervisor_id'],
            'visiting_place' => $data['visiting_place'],
            'visit_date' => $data['visit_date'],
            'visit_time' => $data['visit_time'],
            'visit_duration' => $data['visit_duration'],
            'notes' => $data['notes'] ?? null,
        ];

        $fieldVisitIds = DB::transaction(function () use ($studentCompanies, $visitData): array {
            return $studentCompanies
                ->map(function (StudentCompany $studentCompany) use ($visitData): int {
                    $fieldVisit = FieldVisit::create([
                        ...$visitData,
                        'student_company_id' => $studentCompany->id,
                        'created_by' => auth()->id(),
                    ]);

                    return $fieldVisit->id;
                })
                ->all();
        });

        $fieldVisits = FieldVisit::query()
            ->whereIn('id', $fieldVisitIds)
            ->with([
                'studentCompany.student.media',
                'studentCompany.student.studentProfile.major',
                'studentCompany.company',
                'studentCompany.branch',
                'studentCompany.department',
                'supervisor',
                'createdBy',
            ])
            ->get();

        return $this->successResponse(
            FieldVisitResource::collection($fieldVisits),
            __('Field Visits created successfully'),
            201
        );
    }

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/field-visits/{fieldVisit}",
     * summary="Get field visit details",
     * tags={"Field Visits"},
     * security={{"sanctum": {}}},
     *
     * @OA\Parameter(name="fieldVisit", in="path", required=true, @OA\Schema(type="integer")),
     *
     * @OA\Response(response=200, description="Success")
     * )
     */
    public function show(FieldVisit $fieldVisit)
    {
        $fieldVisit = QueryBuilder::for($this->visibleFieldVisitsQuery())
            ->where('id', $fieldVisit->id)
            ->allowedFields(FieldVisitResource::allowedFields())
            ->allowedIncludes(FieldVisitResource::allowedIncludes())
            ->firstOrFail();

        return $this->successResponse(
            new FieldVisitResource($fieldVisit),
            __('Field Visit retrieved successfully')
        );
    }

    /**
     * @OA\Post(
     * path="/api/v1/ppuds/field-visits/{fieldVisit}",
     * summary="Update field visit",
     * description="Update field visit details. Use _method=PATCH for multipart support.",
     * tags={"Field Visits"},
     * security={{"sanctum": {}}},
     *
     * @OA\Parameter(name="fieldVisit", in="path", required=true, @OA\Schema(type="integer")),
     *
     * @OA\RequestBody(
     *
     * @OA\MediaType(
     * mediaType="multipart/form-data",
     *
     * @OA\Schema(
     *
     * @OA\Property(property="_method", type="string", example="PATCH"),
     * @OA\Property(property="visiting_place", type="string", example="Branch Office"),
     * @OA\Property(property="notes", type="string", example="Updated notes"),
     * @OA\Property(property="visit_duration", type="integer", example=90),
     * @OA\Property(property="attachment", type="string", format="binary", description="Optional single new attachment"),
     * @OA\Property(property="image", type="string", format="binary", description="Optional single new image attachment"),
     * @OA\Property(
     * property="attachments",
     * type="array",
     * @OA\Items(type="string", format="binary"),
     * description="Optional new attachments. In Postman send as attachments[] for multiple files."
     * ),
     * @OA\Property(
     * property="images",
     * type="array",
     * @OA\Items(type="string", format="binary"),
     * description="Optional new image attachments. In Postman send as images[] for multiple files."
     * )
     * )
     * )
     * ),
     *
     * @OA\Response(response=200, description="Updated successfully")
     * )
     */
    public function update(FieldVisitUpdate $request, FieldVisit $fieldVisit)
    {
        $data = $request->validated();
        $attachments = $request->attachmentFiles();
        unset($data['attachment'], $data['image'], $data['attachments'], $data['images']);

        if (! $this->canAccessStudentCompanyRecord($fieldVisit->student_company_id)) {
            return $this->errorResponse(__('You are not authorized to access this student company.'), 403);
        }

        if ($response = $this->ensureRelatedStudentCompanyInCurrentSemester($fieldVisit)) {
            return $response;
        }

        if ($request->filled('student_company_id')) {
            if (! $this->canAccessStudentCompanyRecord((int) $request->student_company_id)) {
                return $this->errorResponse(__('You are not authorized to access this student company.'), 403);
            }

            if ($response = $this->ensureStudentCompanyInCurrentSemester((int) $request->student_company_id)) {
                return $response;
            }
        }

        $studentCompanyId = (int) ($data['student_company_id'] ?? $fieldVisit->student_company_id);
        $supervisorId = (int) ($data['supervisor_id'] ?? $fieldVisit->supervisor_id);

        if (! $this->studentCompanyBelongsToSupervisor($studentCompanyId, $supervisorId)) {
            return $this->errorResponse(__('The selected student does not belong to the selected supervisor.'), 422);
        }

        $fieldVisit->update($data);
        $this->addAttachments($fieldVisit, $attachments);

        return $this->successResponse(
            new FieldVisitResource($fieldVisit->refresh()->loadMissing('media')),
            __('Field Visit updated successfully')
        );
    }

    private function visibleFieldVisitsQuery(): Builder
    {
        return FieldVisit::query()
            ->with('media')
            ->whereHas('studentCompany', function (Builder $query): void {
                $this->applyStudentCompanyVisibilityScope($query);
            });
    }

    private function currentSemesterStudentCompanyQuery(): Builder
    {
        $settings = app(GeneralSettings::class);

        return $this->applyStudentCompanyVisibilityScope(
            StudentCompany::query()
                ->whereHas('registration', function (Builder $query) use ($settings): void {
                    $query
                        ->where('year', $settings->year)
                        ->where('semester', $settings->semester_type->value);
                })
        );
    }

    private function studentCompanyBelongsToSupervisor(int $studentCompanyId, int $supervisorId): bool
    {
        return StudentCompany::query()
            ->whereKey($studentCompanyId)
            ->whereHas('registration', function (Builder $query) use ($supervisorId): void {
                $query->where('supervisor_id', $supervisorId);
            })
            ->exists();
    }

    private function addAttachments(FieldVisit $fieldVisit, array $attachments): void
    {
        foreach (array_filter($attachments) as $attachment) {
            $fieldVisit->addAttachment($attachment);
        }
    }
}
