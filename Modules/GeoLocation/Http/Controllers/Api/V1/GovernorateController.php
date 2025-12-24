<?php

namespace Modules\GeoLocation\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Modules\Core\Traits\ApiResponse;
use Modules\GeoLocation\Entities\Governorate; // تم التغيير
use Modules\GeoLocation\Transformers\V1\GovernorateResource; // تم التغيير
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @OA\Schema(
 * schema="GovernorateResource",
 * title="Governorate Resource",
 * description="Governorate resource model",
 * @OA\Property(property="id", type="integer", example=1),
 * @OA\Property(property="name", type="string", example="Gaza Governorate"),
 * @OA\Property(property="code", type="string", example="GZ"),
 * @OA\Property(property="country_id", type="integer", example=1),
 * @OA\Property(
 * property="country",
 * description="Country object (loaded via ?include=country)",
 * type="object",
 * example={"id": 1, "name": "Palestine"}
 * ),
 * @OA\Property(
 * property="cities",
 * description="List of cities (loaded via ?include=cities)",
 * type="array",
 * @OA\Items(type="object", example={"id": 1, "name": "Gaza City"})
 * )
 * )
 */
class GovernorateController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     * path="/api/v1/geolocation/governorates",
     * summary="Get all Governorates",
     * description="Retrieve a list of all governorates with filtering, sorting, and pagination",
     * tags={"Geolocation"},
     * security={{"sanctum": {}}},
     * @OA\Parameter(
     * name="Accept-Language",
     * in="header",
     * required=true,
     * description="Language header (ar or en)",
     * @OA\Schema(type="string", default="ar")
     * ),
     * @OA\Parameter(
     * name="per_page",
     * in="query",
     * required=false,
     * description="Number of items per page",
     * @OA\Schema(type="integer", example=15)
     * ),
     * @OA\Parameter(
     * name="filter[id]", in="query", required=false, description="Filter by exact ID",
     * @OA\Schema(type="integer")
     * ),
     * @OA\Parameter(
     * name="filter[name]", in="query", required=false, description="Filter by partial name",
     * @OA\Schema(type="string")
     * ),
     * @OA\Parameter(
     * name="filter[code]", in="query", required=false, description="Filter by partial code",
     * @OA\Schema(type="string")
     * ),
     * @OA\Parameter(
     * name="filter[country_id]", in="query", required=false, description="Filter by exact country ID",
     * @OA\Schema(type="integer")
     * ),
     * @OA\Parameter(
     * name="sort",
     * in="query",
     * required=false,
     * description="Sort by fields (comma-separated). Allowed: id, name, code, created_at. Prepend '-' for descending.",
     * @OA\Schema(type="string", example="-created_at,name")
     * ),
     * @OA\Parameter(
     * name="include",
     * in="query",
     * required=false,
     * description="Include related resources (comma-separated). Allowed: country, cities",
     * @OA\Schema(type="string", example="country,cities")
     * ),
     * @OA\Parameter(
     * name="fields[governorates]",
     * in="query",
     * required=false,
     * description="Select specific fields (comma-separated). Allowed: id, name, code, country_id, country, cities",
     * @OA\Schema(type="string", example="id,name,country")
     * ),
     * @OA\Response(
     * response=200,
     * description="Governorates retrieved successfully",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="message", type="string", example="Governorates retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="array",
     * @OA\Items(ref="#/components/schemas/GovernorateResource")
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

        $governorates = QueryBuilder::for(Governorate::class)
            ->allowedFilters(GovernorateResource::allowedFilters())
            ->allowedSorts(GovernorateResource::allowedSorts())
            ->allowedFields(GovernorateResource::allowedFields())
            ->allowedIncludes(GovernorateResource::allowedIncludes())
            ->paginate($perPage)
            ->appends(request()->query());

        return $this->successResponse(
            GovernorateResource::collection($governorates),
            __('Governorates retrieved successfully')
        );
    }

    /**
     * @OA\Get(
     * path="/api/v1/geolocation/governorates/{governorate}",
     * summary="Get a single governorate",
     * description="Retrieve details of a specific governorate by ID, with includes and sparse fieldsets",
     * tags={"Geolocation"},
     * security={{"sanctum": {}}},
     * @OA\Parameter(
     * name="Accept-Language",
     * in="header",
     * required=true,
     * description="Language header (ar or en)",
     * @OA\Schema(type="string", default="ar")
     * ),
     * @OA\Parameter(
     * name="governorate",
     * in="path",
     * required=true,
     * description="Governorate ID",
     * @OA\Schema(type="integer")
     * ),
     * @OA\Parameter(
     * name="include",
     * in="query",
     * required=false,
     * description="Include related resources (comma-separated). Allowed: country, cities",
     * @OA\Schema(type="string", example="country,cities")
     * ),
     * @OA\Parameter(
     * name="fields[governorates]",
     * in="query",
     * required=false,
     * description="Select specific fields (comma-separated). Allowed: id, name, code, country_id, country, cities",
     * @OA\Schema(type="string", example="id,name,country")
     * ),
     * @OA\Response(
     * response=200,
     * description="Governorate retrieved successfully",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="message", type="string", example="Governorate retrieved successfully"),
     * @OA\Property(property="data", ref="#/components/schemas/GovernorateResource")
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=404, description="Governorate not found")
     * )
     */
    public function show($governorate)
    {
        $query = QueryBuilder::for(Governorate::class)
            ->allowedFields(GovernorateResource::allowedFields())
            ->allowedIncludes(GovernorateResource::allowedIncludes());

        $governorateInstance = $query->findOrFail($governorate);

        return $this->successResponse(
            new GovernorateResource($governorateInstance),
            __('Governorate retrieved successfully')
        );
    }
}
