<?php

namespace Modules\Branch\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Branch\Entities\Branch;
use Modules\Core\Entities\User;
use Modules\Core\Http\Controllers\Api\V1\Auth\LoginController;
use Modules\Core\Http\Requests\UserRequest;
use Modules\Core\Traits\ApiResponse;
use Modules\Branch\Transformers\V1\BranchResource;
use Spatie\QueryBuilder\QueryBuilder;

class BranchController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     *     path="/api/v1/branches",
     *     summary="Get all branches",
     *     description="Retrieve a list of all branches",
     *     tags={"Branch"},
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
     *     name="filter[id]",
     *     in="query",
     *     required=false,
     *     description="Filter by id (exact)",
     *     @OA\Schema(type="integer")
     *   ),
     *  @OA\Parameter(
     *    name="filter[name]",
     *    in="query",
     *    required=false,
     *    description="Filter by translated name (LIKE)",
     *    @OA\Schema(type="string")
     *  ),
     *     @OA\Parameter(
     *     name="filter[address]",
     *     in="query",
     *     required=false,
     *     description="Filter by translated address (LIKE)",
     *     @OA\Schema(type="string")
     *   ),
     *     @OA\Parameter(
     *      name="filter[phone]",
     *      in="query",
     *      required=false,
     *      description="Filter by Phone",
     *      @OA\Schema(type="string")
     *    ),
     *     @OA\Parameter(
     *      name="filter[email]",
     *      in="query",
     *      required=false,
     *      description="Filter by Email",
     *      @OA\Schema(type="string")
     *    ),
     *     @OA\Parameter(
     *      name="filter[city_id]",
     *      in="query",
     *      required=false,
     *      description="Filter by City ID",
     *      @OA\Schema(type="integer")
     *    ),
     *     @OA\Parameter(
     *      name="filter[country_id]",
     *      in="query",
     *      required=false,
     *      description="Filter by Country ID",
     *      @OA\Schema(type="integer")
     *    ),
     *
     *  @OA\Parameter(
     *    name="filter[status]",
     *    in="query",
     *    required=false,
     *    description="Filter by status (exact)",
     *    @OA\Schema(type="int" , example=1)
     * ),
     *     @OA\Parameter(
     *      name="filter[opening_time]",
     *      in="query",
     *      required=false,
     *      description="Filter by Opening Time",
     *      @OA\Schema(type="string")
     *    ),
     *     @OA\Parameter(
     *      name="filter[closing_time]",
     *      in="query",
     *      required=false,
     *      description="Filter by Closing Time",
     *      @OA\Schema(type="string")
     *    ),
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
     *         description="Number of branches per page",
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
     *         description="Branches retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Branches retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Befound Online PS الفرع الرئيسي"),
     *                     @OA\Property(property="description", type="string", example="هذا الفرع الرئيسي للشركة"),
     *                     @OA\Property(property="address", type="string", example="الخليل ،مفرق الميزان"),
     *                     @OA\Property(property="status", type="integer", example=1),
     *                     @OA\Property(property="phone", type="string", example="0569162687"),
     *                     @OA\Property(property="email", type="string", example="maraqamohamad@gmail.com"),
     *                     @OA\Property(property="city_id", type="integer", example="1"),
     *                     @OA\Property(property="country_id", type="integer", example="1"),
     *                     @OA\Property(property="latitude", type="string", example="31.234567"),
     *                     @OA\Property(property="longitude", type="string", example="31.234567"),
     *                     @OA\Property(property="opening_time", type="string", example="08:00"),
     *                     @OA\Property(property="closing_time", type="string", example="17:00"),
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

        $categories = QueryBuilder::for(Branch::class)
            ->allowedFields(BranchResource::allowedFields())
            ->allowedFilters(BranchResource::allowedFilters())
            ->allowedSorts(BranchResource::allowedSorts())
            ->paginate($perPage)
            ->appends(request()->query());

        return $this->successResponse(
            BranchResource::collection($categories),
            __('Branches retrieved successfully')
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/branches/{branch}",
     *     summary="Get a single branch",
     *     description="Retrieve details of a specific branch by ID",
     *     tags={"Branch"},
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
     *         name="branch",
     *         in="path",
     *         required=true,
     *         description="Branch ID",
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
     *         description="Branch retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Branch retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Branch Name"),
     *                 @OA\Property(property="description", type="string", example="Branch Description")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Branch not found")
     * )
     */
    public function show(Branch $category)
    {
        $category->load(['media', 'translations']);
        return $this->successResponse(
            new BranchResource($category),
            __('Branch retrieved successfully')
        );
    }
}
