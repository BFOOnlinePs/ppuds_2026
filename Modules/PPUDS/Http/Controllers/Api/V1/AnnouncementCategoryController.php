<?php

namespace Modules\PPUDS\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Core\Traits\ApiResponse;
use Modules\PPUDS\Entities\AnnouncementCategory;
use Modules\PPUDS\Http\Requests\AnnouncementCategoryRequest;
use Modules\PPUDS\Transformers\V1\AnnouncementCategoryResource;
use Spatie\QueryBuilder\QueryBuilder;

class AnnouncementCategoryController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/announcement-categories",
     * summary="Get all announcement categories",
     * description="Retrieve a list of all announcement categories",
     * tags={"Announcement Categories"},
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
     * description="Announcement categories retrieved successfully",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Announcement categories retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="array",
     * @OA\Items(ref="#/components/schemas/AnnouncementCategoryResource")
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

        $categories = QueryBuilder::for(AnnouncementCategory::class)
            ->allowedFields(AnnouncementCategoryResource::allowedFields())
            ->allowedFilters(AnnouncementCategoryResource::allowedFilters())
            ->allowedSorts(AnnouncementCategoryResource::allowedSorts())
            ->with(['translations'])
            ->paginate($perPage)
            ->appends(request()->query());

        return $this->successResponse(
            AnnouncementCategoryResource::collection($categories),
            __('Announcement categories retrieved successfully')
        );
    }

    /**
     * @OA\Post(
     * path="/api/v1/ppuds/announcement-categories",
     * summary="Create a new announcement category",
     * description="Creates a new category for announcements.",
     * tags={"Announcement Categories"},
     * security={{"sanctum": {}}},
     * @OA\RequestBody(
     * required=true,
     * @OA\MediaType(
     * mediaType="application/json",
     * @OA\Schema(
     * required={"name"},
     * @OA\Property(property="name", type="string", example="General", description="Category name")
     * )
     * )
     * ),
     * @OA\Response(
     * response=201,
     * description="Announcement category created successfully",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Announcement category created successfully"),
     * @OA\Property(property="data", ref="#/components/schemas/AnnouncementCategoryResource")
     * )
     * ),
     * @OA\Response(response=422, description="Validation Error")
     * )
     */
    public function store(AnnouncementCategoryRequest $request)
    {
        $category = DB::transaction(function () use ($request) {
            $data = $request->validated();
            $data['created_by'] = auth()->id();

            return AnnouncementCategory::create($data);
        });

        $category->load(['translations']);

        return $this->successResponse(
            new AnnouncementCategoryResource($category),
            __('Announcement category created successfully'),
            201
        );
    }

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/announcement-categories/{announcement_category}",
     * summary="Get a single announcement category",
     * description="Retrieve details of a specific announcement category by ID",
     * tags={"Announcement Categories"},
     * security={{"sanctum": {}}},
     * @OA\Parameter(
     * name="Accept-Language",
     * in="header",
     * required=true,
     * description="Language header (ar or en)",
     * @OA\Schema(type="string", default="ar", example="en")
     * ),
     * @OA\Parameter(
     * name="announcement_category",
     * in="path",
     * required=true,
     * description="Announcement category ID",
     * @OA\Schema(type="integer", example=1)
     * ),
     * @OA\Response(
     * response=200,
     * description="Announcement category retrieved successfully",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Announcement category retrieved successfully"),
     * @OA\Property(property="data", ref="#/components/schemas/AnnouncementCategoryResource")
     * )
     * ),
     * @OA\Response(response=404, description="Announcement category not found")
     * )
     */
    public function show(AnnouncementCategory $announcementCategory)
    {
        $category = QueryBuilder::for(AnnouncementCategory::class)
            ->where('id', $announcementCategory->id)
            ->allowedFields(AnnouncementCategoryResource::allowedFields())
            ->with(['translations'])
            ->firstOrFail();

        return $this->successResponse(
            new AnnouncementCategoryResource($category),
            __('Announcement category retrieved successfully')
        );
    }
}
