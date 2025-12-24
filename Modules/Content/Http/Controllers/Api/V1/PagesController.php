<?php

namespace Modules\Content\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Content\Entities\Page; // <-- تم التغيير إلى Page
use Modules\Content\Transformers\V1\PageResource; // <-- المورد الكامل (للتفاصيل)
use Modules\Content\Http\Resources\PageListResource; // <-- المورد الخفيف (للقائمة)
use Modules\Core\Traits\ApiResponse;
use Spatie\QueryBuilder\QueryBuilder;

class PagesController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     * path="/api/v1/content/pages",
     * summary="Get all pages (list)",
     * description="Retrieve a list of all static pages (name and slug only)",
     * tags={"Content"},
     * security={{"sanctum": {}}},
     * @OA\Parameter(
     * name="Accept-Language",
     * in="header",
     * required=true,
     * description="Language header (ar or en)",
     * @OA\Schema(type="string", default="ar")
     * ),
     * @OA\Parameter(
     * name="fields[pages]",
     * in="query",
     * required=false,
     * description="Comma separated list of fields (id,slug,name)",
     * @OA\Schema(type="string", example="id,name")
     * ),
     * @OA\Parameter(
     * name="filter[name]",
     * in="query",
     * required=false,
     * description="Filter by translated name (LIKE)",
     * @OA\Schema(type="string")
     * ),
     * @OA\Parameter(
     * name="filter[slug]",
     * in="query",
     * required=false,
     * description="Filter by slug (exact)",
     * @OA\Schema(type="string")
     * ),
     * @OA\Parameter(
     * name="filter[id]",
     * in="query",
     * required=false,
     * description="Filter by id (exact)",
     * @OA\Schema(type="integer")
     * ),
     * @OA\Parameter(
     * name="sort",
     * in="query",
     * required=false,
     * description="Sort fields. Use leading '-' for DESC. (id, slug, name)",
     * @OA\Schema(type="string", example="-name")
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
     * description="Pages list retrieved successfully",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Pages retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="array",
     * @OA\Items(ref="#/components/schemas/PageResource")
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

        $pages = QueryBuilder::for(Page::class)
            ->allowedFields(PageResource::allowedFields())
            ->allowedFilters(PageResource::allowedFilters())
            ->allowedSorts(PageResource::allowedSorts())
            // ->where('status', 'published') // يمكنك إضافة هذا لاحقاً
            ->paginate($perPage)
            ->appends(request()->query());

        return $this->successResponse(
            PageResource::collection($pages),
            __('Pages retrieved successfully')
        );
    }

    /**
     * @OA\Get(
     * path="/api/v1/content/pages/{slug}",
     * summary="Get a single page details",
     * description="Retrieve details of a single page by slug (including content)",
     * tags={"Content"},
     * security={{"sanctum": {}}},
     * @OA\Parameter(
     * name="Accept-Language",
     * in="header",
     * required=true,
     * description="Language header (ar or en)",
     * @OA\Schema(type="string", default="ar")
     * ),
     * @OA\Parameter(
     * name="slug",
     * in="path",
     * required=true,
     * description="The slug of the page (e.g., 'privacy-policy')",
     * @OA\Schema(type="string")
     * ),
     * @OA\Parameter(
     * name="fields[pages]",
     * in="query",
     * required=false,
     * description="Comma separated list of fields (id,slug,name,content)",
     * @OA\Schema(type="string")
     * ),
     * @OA\Response(
     * response=200,
     * description="Page details retrieved successfully",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Page retrieved successfully"),
     * @OA\Property(
     * property="data",
     * ref="#/components/schemas/PageResource"
     * )
     * )
     * ),
     * @OA\Response(response=404, description="Page not found"),
     * @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function show($slug)
    {
        $page = QueryBuilder::for(Page::class)
            ->allowedFields(PageResource::allowedFields())
            ->where('slug', $slug)
            ->first();

        if (!$page) {
            return $this->errorResponse(__('Page not found'), 404);
        }

        return $this->successResponse(
            new PageResource($page),
            __('Page retrieved successfully')
        );
    }
}
