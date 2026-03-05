<?php

namespace Modules\Items\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Modules\Core\Traits\ApiResponse;
use Modules\Items\Entities\Addon;
use Modules\Items\Transformers\V1\AddonResource;
use Spatie\QueryBuilder\QueryBuilder;

class AddonController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     * path="/api/v1/items/addons",
     * summary="Get all addons",
     * description="Retrieve a list of all addons with their options",
     * tags={"Items"},
     * security={{"sanctum": {}}},
     * @OA\Parameter(
     * name="Accept-Language",
     * in="header",
     * required=true,
     * description="Language header (ar or en)",
     * @OA\Schema(type="string", default="ar")
     * ),
     * @OA\Parameter(
     * name="filter[name]",
     * in="query",
     * required=false,
     * description="Filter addons by name",
     * @OA\Schema(type="string")
     * ),
     * @OA\Parameter(
     * name="per_page",
     * in="query",
     * required=false,
     * description="Number of items per page",
     * @OA\Schema(type="integer", example=15)
     * ),
     * @OA\Response(
     * response=200,
     * description="Addons retrieved successfully",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="message", type="string", example="Addons retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="array",
     * @OA\Items(
     * type="object",
     * @OA\Property(property="id", type="integer", example=1),
     * @OA\Property(property="name", type="string", example="Size"),
     * @OA\Property(property="type", type="string", example="Radio"),
     * @OA\Property(property="image", type="string", example="http://.../image.png"),
     * @OA\Property(
     * property="options",
     * type="array",
     * @OA\Items(
     * type="object",
     * @OA\Property(property="id", type="integer", example=10),
     * @OA\Property(property="name", type="string", example="Small")
     * )
     * )
     * )
     * )
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index()
    {
        $defaultPerPage = config('core.pagination.per_page', 15);
        $maxPerPage = config('core.pagination.max_per_page', 100);
        $perPage = min(request('per_page', $defaultPerPage), $maxPerPage);
        
        $addons = QueryBuilder::for(Addon::class)
            ->allowedFilters(['name'])
            ->with(['addonOptions'])
            ->paginate($perPage)
            ->appends(request()->query());

        return $this->successResponse(
            AddonResource::collection($addons),
            __('Addons retrieved successfully')
        );
    }

    /**
     * @OA\Get(
     * path="/api/v1/items/addons/{addon}",
     * summary="Get a single addon",
     * description="Retrieve details of a specific addon by ID, including its options",
     * tags={"Items"},
     * security={{"sanctum": {}}},
     * @OA\Parameter(
     * name="Accept-Language",
     * in="header",
     * required=true,
     * description="Language header (ar or en)",
     * @OA\Schema(type="string", default="ar")
     * ),
     * @OA\Parameter(
     * name="addon",
     * in="path",
     * required=true,
     * description="Addon ID",
     * @OA\Schema(type="integer")
     * ),
     * @OA\Response(
     * response=200,
     * description="Addon retrieved successfully",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="message", type="string", example="Addon retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="object",
     * @OA\Property(property="id", type="integer", example=1),
     * @OA\Property(property="name", type="string", example="Size"),
     * @OA\Property(property="type", type="string", example="Radio"),
     * @OA\Property(property="image", type="string", example="http://.../image.png"),
     * @OA\Property(
     * property="options",
     * type="array",
     * @OA\Items(
     * type="object",
     * @OA\Property(property="id", type="integer", example=10),
     * @OA\Property(property="name", type="string", example="Small")
     * )
     * )
     * )
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=404, description="Addon not found")
     * )
     */
    public function show(Addon $addon)
    {
        $addon->load(['options']); // Eager-load the options relationship
        return $this->successResponse(
            new AddonResource($addon),
            __('Addon retrieved successfully')
        );
    }
}
