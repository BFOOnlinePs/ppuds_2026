<?php

namespace Modules\Items\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Core\Entities\User;
use Modules\Core\Http\Controllers\Api\V1\Auth\LoginController;
use Modules\Core\Http\Requests\UserRequest;
use Modules\Core\Traits\ApiResponse;
use Modules\Items\Http\Requests\CategoryRequest;
use Modules\Items\Entities\Category;
use Modules\Items\Transformers\V1\CategoryResource;
use Spatie\QueryBuilder\QueryBuilder;

class CategoryController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     *     path="/api/v1/items/categories",
     *     summary="Get all categories",
     *     description="Retrieve a list of all categories",
     *     tags={"Items"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *          name="Accept-Language",
     *          in="header",
     *          required=true,
     *          description="Language header (ar or en)",
     *          @OA\Schema(
     *              type="string",
     *              default="ar",
     *              example="en"
     *          )
     *      ),
     *     @OA\Parameter(
     *         name="fields",
     *         in="query",
     *         required=false,
     *         description="Comma separated list of fields to be returned",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *    name="filter[slug]",
     *    in="query",
     *    required=false,
     *    description="Filter by slug (exact or partial depending on your filter)",
     *    @OA\Schema(type="string")
     *  ),
     *  @OA\Parameter(
     *    name="filter[name]",
     *    in="query",
     *    required=false,
     *    description="Filter by translated name (LIKE)",
     *    @OA\Schema(type="string")
     *  ),
     *  @OA\Parameter(
     *    name="filter[id]",
     *    in="query",
     *    required=false,
     *    description="Filter by id (exact)",
     *    @OA\Schema(type="integer")
     *  ),
     *  @OA\Parameter(
     *    name="filter[status]",
     *    in="query",
     *    required=false,
     *    description="Filter by status (exact)",
     *    @OA\Schema(type="string")
     * ),
     *  @OA\Parameter(
     *     name="filter[parent_id]",
     *     in="query",
     *     required=false,
     *     description="Filter by parent_id (exact)",
     *     @OA\Schema(type="string")
     *  ),
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         required=false,
     *         description="Sort fields. Use leading '-' for DESC. Examples: name, -name, slug, -id",
     *         @OA\Schema(type="string", example="asc")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Number of items per page",
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Categories retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Categories retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Category Name"),
     *                     @OA\Property(property="description", type="string", example="Category Description")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index()
    {
        $defaultPerPage = config('core.pagination.per_page');
        $maxPerPage = config('core.pagination.max_per_page');
        $perPage = min(request('per_page', $defaultPerPage), $maxPerPage);

        $categories = QueryBuilder::for(Category::class)
            ->allowedFields(CategoryResource::allowedFields())
            ->allowedFilters(CategoryResource::allowedFilters())
            ->allowedSorts(CategoryResource::allowedSorts())
            ->with(['media'])
            ->paginate($perPage)
            ->appends(request()->query());

        return $this->successResponse(
            CategoryResource::collection($categories),
            __('Categories retrieved successfully')
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/items/categories/{category}",
     *     summary="Get a single category",
     *     description="Retrieve details of a specific category by ID",
     *     tags={"Items"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *           name="Accept-Language",
     *           in="header",
     *           required=true,
     *           description="Language header (ar or en)",
     *           @OA\Schema(
     *               type="string",
     *               default="ar",
     *               example="en"
     *           )
     *       ),
     *     @OA\Parameter(
     *         name="category",
     *         in="path",
     *         required=true,
     *         description="Category ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *          name="fields",
     *          in="path",
     *          required=false,
     *          description="Comma separated list of fields to be returned",
     *          @OA\Schema(type="string", example="id,name,description")
     *      ),
     *     @OA\Response(
     *         response=200,
     *         description="Category retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Category retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Category Name"),
     *                 @OA\Property(property="description", type="string", example="Category Description")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Category not found")
     * )
     */
    public function show(Category $category)
    {
        $category->load(['media', 'translations']);
        return $this->successResponse(
            new CategoryResource($category),
            __('Category retrieved successfully')
        );
    }

    public function store(CategoryRequest $request)
    {

    }

    public function update(CategoryRequest $request, User $category)
    {

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
    }
}
