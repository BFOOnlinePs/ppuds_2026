<?php

namespace Modules\PPUDS\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Core\Traits\ApiResponse;
use Modules\PPUDS\Entities\CompanyCategory;
use Modules\PPUDS\Http\Requests\CompanyCategoryRequest; // تأكد من إنشاء هذا الملف
use Modules\PPUDS\Transformers\V1\CompanyCategoryResource;
use Spatie\QueryBuilder\QueryBuilder;

class CompanyCategoryController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/company-categories",
     * summary="Get all company categories",
     * description="Retrieve a list of all company categories",
     * tags={"Company Categories"},
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
     * description="Sort fields. Use leading '-' for DESC. Examples: id, -created_at",
     * @OA\Schema(type="string", example="-id")
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
     * description="Company categories retrieved successfully",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Company categories retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="array",
     * @OA\Items(
     * type="object",
     * @OA\Property(property="id", type="integer", example=1),
     * @OA\Property(property="name", type="string", example="Technology"),
     * @OA\Property(property="created_at", type="string", example="2023-01-01 12:00:00")
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

        $categories = QueryBuilder::for(CompanyCategory::class)
            ->allowedFields(CompanyCategoryResource::allowedFields())
            ->allowedFilters(CompanyCategoryResource::allowedFilters())
            ->allowedSorts(CompanyCategoryResource::allowedSorts())
            ->with(['translations']) // تحميل الترجمات لتقليل الاستعلامات
            ->paginate($perPage)
            ->appends(request()->query());

        return $this->successResponse(
            CompanyCategoryResource::collection($categories),
            __('Company categories retrieved successfully')
        );
    }

    /**
     * @OA\Post(
     * path="/api/v1/ppuds/company-categories",
     * summary="Create a new company category",
     * description="Creates a new category for companies.",
     * tags={"Company Categories"},
     * security={{"sanctum": {}}},
     * @OA\RequestBody(
     * required=true,
     * @OA\MediaType(
     * mediaType="application/json",
     * @OA\Schema(
     * required={"name"},
     * @OA\Property(property="name", type="string", example="Technology", description="Category Name"),
     * )
     * )
     * ),
     * @OA\Response(
     * response=201,
     * description="Category created successfully",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Category created successfully"),
     * @OA\Property(
     * property="data",
     * type="object",
     * @OA\Property(property="id", type="integer", example=1),
     * @OA\Property(property="name", type="string", example="Technology")
     * )
     * )
     * ),
     * @OA\Response(response=422, description="Validation Error")
     * )
     */
    public function store(CompanyCategoryRequest $request)
    {
        $category = DB::transaction(function () use ($request) {

            $data = $request->validated();
            $data['created_by'] = auth()->id();

            $category = CompanyCategory::create($data);

            return $category;
        });

        $category->load(['translations']);

        return $this->successResponse(
            new CompanyCategoryResource($category),
            __('Category created successfully'),
            201
        );
    }

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/company-categories/{company_category}",
     * summary="Get a single company category",
     * description="Retrieve details of a specific category by ID",
     * tags={"Company Categories"},
     * security={{"sanctum": {}}},
     * @OA\Parameter(
     * name="Accept-Language",
     * in="header",
     * required=true,
     * description="Language header (ar or en)",
     * @OA\Schema(type="string", default="ar", example="en")
     * ),
     * @OA\Parameter(
     * name="company_category",
     * in="path",
     * required=true,
     * description="Category ID",
     * @OA\Schema(type="integer", example=1)
     * ),
     * @OA\Response(
     * response=200,
     * description="Category retrieved successfully",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Category retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="object",
     * @OA\Property(property="id", type="integer", example=1),
     * @OA\Property(property="name", type="string", example="Technology")
     * )
     * )
     * ),
     * @OA\Response(response=404, description="Category not found")
     * )
     */
    public function show(CompanyCategory $companyCategory)
    {
        $category = QueryBuilder::for(CompanyCategory::class)
            ->where('id', $companyCategory->id)
            ->allowedFields(CompanyCategoryResource::allowedFields())
            ->with(['translations'])
            ->firstOrFail();

        return $this->successResponse(
            new CompanyCategoryResource($category),
            __('Category retrieved successfully')
        );
    }
}
