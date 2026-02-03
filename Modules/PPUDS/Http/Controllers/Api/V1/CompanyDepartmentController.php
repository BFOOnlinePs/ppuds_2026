<?php

namespace Modules\PPUDS\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Core\Traits\ApiResponse;
use Modules\PPUDS\Entities\CompanyDepartment;
use Modules\PPUDS\Http\Requests\CompanyDepartmentRequest; // تم التعديل هنا
use Modules\PPUDS\Transformers\V1\CompanyDepartmentResource;
use Spatie\QueryBuilder\QueryBuilder;

class CompanyDepartmentController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/company-departments",
     * summary="Get all company departments",
     * description="Retrieve a list of all company departments",
     * tags={"Company Departments"},
     * security={{"sanctum": {}}},
     * @OA\Parameter(
     * name="Accept-Language",
     * in="header",
     * required=true,
     * description="Language header (ar or en)",
     * @OA\Schema(type="string", default="ar", example="en")
     * ),
     * @OA\Parameter(
     * name="fields",
     * in="query",
     * required=false,
     * description="Comma separated list of fields to be returned",
     * @OA\Schema(type="string")
     * ),
     * @OA\Parameter(
     * name="filter[branch_id]",
     * in="query",
     * required=false,
     * description="Filter by Branch ID",
     * @OA\Schema(type="integer")
     * ),
     * @OA\Parameter(
     * name="filter[name]",
     * in="query",
     * required=false,
     * description="Filter by translated name (LIKE)",
     * @OA\Schema(type="string")
     * ),
     * @OA\Parameter(
     * name="sort",
     * in="query",
     * required=false,
     * description="Sort fields. Use leading '-' for DESC. Examples: name, -id",
     * @OA\Schema(type="string", example="name")
     * ),
     * @OA\Parameter(
     * name="per_page",
     * in="query",
     * required=false,
     * description="Number of items per page",
     * @OA\Schema(type="integer", example=10)
     * ),
     * @OA\Parameter(
     * name="page",
     * in="query",
     * required=false,
     * description="Page number",
     * @OA\Schema(type="integer", example=1)
     * ),
     * @OA\Response(
     * response=200,
     * description="Company departments retrieved successfully",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Company departments retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="array",
     * @OA\Items(
     * type="object",
     * @OA\Property(property="id", type="integer", example=1),
     * @OA\Property(property="name", type="string", example="HR Department"),
     * @OA\Property(property="branch_id", type="integer", example=5),
     * @OA\Property(property="created_at", type="string", example="2023-01-01 10:00:00")
     * )
     * )
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index()
    {
        $defaultPerPage = config('core.pagination.per_page', 10);
        $maxPerPage = config('core.pagination.max_per_page', 100);
        $perPage = min(request('per_page', $defaultPerPage), $maxPerPage);

        $departments = QueryBuilder::for(CompanyDepartment::class)
            ->allowedFields(CompanyDepartmentResource::allowedFields())
            ->allowedFilters(CompanyDepartmentResource::allowedFilters())
            ->allowedSorts(CompanyDepartmentResource::allowedSorts())
            ->with(['translations', 'branch'])
            ->paginate($perPage)
            ->appends(request()->query());

        return $this->successResponse(
            CompanyDepartmentResource::collection($departments),
            __('Company departments retrieved successfully')
        );
    }

    /**
     * @OA\Post(
     * path="/api/v1/ppuds/company-departments",
     * summary="Create a new company department",
     * description="Creates a new department within a specific branch.",
     * tags={"Company Departments"},
     * security={{"sanctum": {}}},
     * @OA\RequestBody(
     * required=true,
     * @OA\MediaType(
     * mediaType="application/json",
     * @OA\Schema(
     * required={"name", "branch_id"},
     * @OA\Property(property="name", type="string", example="IT Department"),
     * @OA\Property(property="branch_id", type="integer", example=1, description="The ID of the branch this department belongs to"),
     * @OA\Property(property="description", type="string", example="Information Technology Department"),
     * @OA\Property(property="is_active", type="boolean", example=true)
     * )
     * )
     * ),
     * @OA\Response(
     * response=201,
     * description="Department created successfully",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Department created successfully"),
     * @OA\Property(
     * property="data",
     * type="object",
     * @OA\Property(property="id", type="integer", example=1),
     * @OA\Property(property="name", type="string", example="IT Department"),
     * @OA\Property(property="branch_id", type="integer", example=1)
     * )
     * )
     * ),
     * @OA\Response(response=422, description="Validation Error")
     * )
     */
    public function store(CompanyDepartmentRequest $request)
    {
        $department = DB::transaction(function () use ($request) {

            $data = $request->validated();
            $data['created_by'] = auth()->id();

            $department = CompanyDepartment::create($data);

            return $department;
        });

        $department->load(['translations']);

        return $this->successResponse(
            new CompanyDepartmentResource($department),
            __('Department created successfully'),
            201
        );
    }

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/company-departments/{department}",
     * summary="Get a single department",
     * description="Retrieve details of a specific department by ID",
     * tags={"Company Departments"},
     * security={{"sanctum": {}}},
     * @OA\Parameter(
     * name="Accept-Language",
     * in="header",
     * required=true,
     * description="Language header (ar or en)",
     * @OA\Schema(type="string", default="ar", example="en")
     * ),
     * @OA\Parameter(
     * name="department",
     * in="path",
     * required=true,
     * description="Department ID",
     * @OA\Schema(type="integer", example=1)
     * ),
     * @OA\Parameter(
     * name="fields",
     * in="query",
     * required=false,
     * description="Comma separated list of fields to be returned",
     * @OA\Schema(type="string", example="id,name,branch_id")
     * ),
     * @OA\Response(
     * response=200,
     * description="Department retrieved successfully",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Department retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="object",
     * @OA\Property(property="id", type="integer", example=1),
     * @OA\Property(property="name", type="string", example="IT Department"),
     * @OA\Property(property="branch_id", type="integer", example=1)
     * )
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=404, description="Department not found")
     * )
     */
    public function show(CompanyDepartment $department)
    {
        $department = QueryBuilder::for(CompanyDepartment::class)
            ->where('id', $department->id)
            ->allowedFields(CompanyDepartmentResource::allowedFields())
            ->with(['translations', 'branch'])
            ->firstOrFail();

        return $this->successResponse(
            new CompanyDepartmentResource($department),
            __('Department retrieved successfully')
        );
    }
}
