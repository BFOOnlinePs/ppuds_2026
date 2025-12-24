<?php

namespace Modules\GeoLocation\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Modules\Core\Traits\ApiResponse;
use Modules\GeoLocation\Entities\City;
use Modules\GeoLocation\Transformers\V1\CityResource;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @OA\Schema(
 * schema="CityResource",
 * title="City Resource",
 * description="City resource model",
 * @OA\Property(property="id", type="integer", example=1),
 * @OA\Property(property="name", type="string", example="Gaza"),
 * @OA\Property(property="governorate_id", type="integer", example=1),
 * @OA\Property(property="latitude", type="number", format="float", example=31.5),
 * @OA\Property(property="longitude", type="number", format="float", example=34.46667),
 * @OA\Property(property="population", type="integer", example=700000),
 * @OA\Property(property="type", type="string", example="city"),
 * @OA\Property(property="is_capital", type="boolean", example=true),
 * @OA\Property(property="capital_type", type="string", example="governorate_capital"),
 * @OA\Property(
 * property="governorate",
 * description="Governorate object (loaded via ?include=governorate)",
 * type="object",
 * example={"id": 1, "name": "Gaza Governorate"}
 * )
 * )
 */
class CityController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     * path="/api/v1/geolocation/cities",
     * summary="Get all Cities",
     * description="Retrieve a list of all cities with filtering, sorting, and pagination",
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
     * name="filter[governorate_id]", in="query", required=false, description="Filter by exact governorate ID",
     * @OA\Schema(type="integer")
     * ),
     * @OA\Parameter(
     * name="filter[type]", in="query", required=false, description="Filter by exact type",
     * @OA\Schema(type="string")
     * ),
     * @OA\Parameter(
     * name="filter[is_capital]", in="query", required=false, description="Filter by capital status (1 or 0)",
     * @OA\Schema(type="boolean")
     * ),
     * @OA\Parameter(
     * name="filter[population]", in="query", required=false, description="Filter by exact population",
     * @OA\Schema(type="integer")
     * ),
     * @OA\Parameter(
     * name="sort",
     * in="query",
     * required=false,
     * description="Sort by fields (comma-separated). Allowed: id, name, population, created_at. Prepend '-' for descending.",
     * @OA\Schema(type="string", example="-created_at,name")
     * ),
     * @OA\Parameter(
     * name="include",
     * in="query",
     * required=false,
     * description="Include related resources (comma-separated). Allowed: governorate",
     * @OA\Schema(type="string", example="governorate")
     * ),
     * @OA\Parameter(
     * name="fields[cities]",
     * in="query",
     * required=false,
     * description="Select specific fields (comma-separated). Allowed: id, name, governorate_id, latitude, longitude, population, type, is_capital, capital_type, governorate",
     * @OA\Schema(type="string", example="id,name,latitude,longitude,governorate")
     * ),
     * @OA\Response(
     * response=200,
     * description="Cities retrieved successfully",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="message", type="string", example="Cities retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="array",
     * @OA\Items(ref="#/components/schemas/CityResource")
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

        $cities = QueryBuilder::for(City::class)
            ->allowedFilters(CityResource::allowedFilters())
            ->allowedSorts(CityResource::allowedSorts())
            ->allowedFields(CityResource::allowedFields())
            ->allowedIncludes(CityResource::allowedIncludes())
            ->paginate($perPage)
            ->appends(request()->query());

        return $this->successResponse(
            CityResource::collection($cities),
            __('Cities retrieved successfully')
        );
    }

    /**
     * @OA\Get(
     * path="/api/v1/geolocation/cities/{city}",
     * summary="Get a single city",
     * description="Retrieve details of a specific city by ID, with includes and sparse fieldsets",
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
     * name="city",
     * in="path",
     * required=true,
     * description="City ID",
     * @OA\Schema(type="integer")
     * ),
     * @OA\Parameter(
     * name="include",
     * in="query",
     * required=false,
     * description="Include related resources (comma-separated). Allowed: governorate",
     * @OA\Schema(type="string", example="governorate")
     * ),
     * @OA\Parameter(
     * name="fields[cities]",
     * in="query",
     * required=false,
     * description="Select specific fields (comma-separated). Allowed: id, name, governorate_id, latitude, longitude, population, type, is_capital, capital_type, governorate",
     * @OA\Schema(type="string", example="id,name,governorate")
     * ),
     * @OA\Response(
     * response=200,
     * description="City retrieved successfully",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="message", type="string", example="City retrieved successfully"),
     * @OA\Property(property="data", ref="#/components/schemas/CityResource")
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=404, description="City not found")
     * )
     */
    public function show($city)
    {
        $query = QueryBuilder::for(City::class)
            ->allowedFields(CityResource::allowedFields())
            ->allowedIncludes(CityResource::allowedIncludes());

        $cityInstance = $query->findOrFail($city);

        return $this->successResponse(
            new CityResource($cityInstance),
            __('City retrieved successfully')
        );
    }
}
