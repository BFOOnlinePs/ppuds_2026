<?php

namespace Modules\PPUDS\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Modules\Core\Traits\ApiResponse;
use Modules\PPUDS\Entities\FieldVisit;
use Modules\PPUDS\Http\Controllers\Api\V1\Concerns\EnsuresCurrentRegistration;
use Modules\PPUDS\Http\Requests\FieldVisitRequest;
use Modules\PPUDS\Http\Requests\FieldVisitUpdate;
use Modules\PPUDS\Transformers\V1\FieldVisitResource;
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
     * description="Filter by Supervisor ID",
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

        $fieldVisits = QueryBuilder::for(FieldVisit::class)
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
     * mediaType="application/json",
     *
     * @OA\Schema(
     * required={"student_company_id", "supervisor_id", "visit_date", "visit_time", "visit_duration"},
     *
     * @OA\Property(property="student_company_id", type="integer", example=1),
     * @OA\Property(property="supervisor_id", type="integer", example=3),
     * @OA\Property(property="visiting_place", type="string", example="Main Office"),
     * @OA\Property(property="visit_date", type="string", format="date", example="2024-05-20"),
     * @OA\Property(property="visit_time", type="string", format="time", example="09:00:00"),
     * @OA\Property(property="visit_duration", type="integer", example=60),
     * @OA\Property(property="notes", type="string", example="Everything went well")
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

        if ($response = $this->ensureStudentCompanyInCurrentSemester((int) $data['student_company_id'])) {
            return $response;
        }

        $data['created_by'] = auth()->id();

        $fieldVisit = FieldVisit::create($data);

        return $this->successResponse(
            new FieldVisitResource($fieldVisit),
            __('Field Visit created successfully'),
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
        $fieldVisit = QueryBuilder::for(FieldVisit::class)
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
     * description="Update field visit details. Use _method=PUT for multipart support.",
     * tags={"Field Visits"},
     * security={{"sanctum": {}}},
     *
     * @OA\Parameter(name="fieldVisit", in="path", required=true, @OA\Schema(type="integer")),
     *
     * @OA\RequestBody(
     *
     * @OA\MediaType(
     * mediaType="application/json",
     *
     * @OA\Schema(
     *
     * @OA\Property(property="_method", type="string", example="PUT"),
     * @OA\Property(property="visiting_place", type="string", example="Branch Office"),
     * @OA\Property(property="notes", type="string", example="Updated notes"),
     * @OA\Property(property="visit_duration", type="integer", example=90)
     * )
     * )
     * ),
     *
     * @OA\Response(response=200, description="Updated successfully")
     * )
     */
    public function update(FieldVisitUpdate $request, FieldVisit $fieldVisit)
    {
        if ($response = $this->ensureRelatedStudentCompanyInCurrentSemester($fieldVisit)) {
            return $response;
        }

        if ($request->filled('student_company_id')) {
            if ($response = $this->ensureStudentCompanyInCurrentSemester((int) $request->student_company_id)) {
                return $response;
            }
        }

        $fieldVisit->update($request->validated());

        return $this->successResponse(
            new FieldVisitResource($fieldVisit->refresh()),
            __('Field Visit updated successfully')
        );
    }
}
