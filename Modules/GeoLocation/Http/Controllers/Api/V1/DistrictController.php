<?php

namespace Modules\GeoLocation\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Modules\Core\Traits\ApiResponse;
use Modules\GeoLocation\Entities\District; // تم التغيير
use Modules\GeoLocation\Transformers\V1\DistrictResource; // تم التغيير
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @OA\Schema(
 * schema="DistrictResource",
 * title="District Resource",
 * description="District resource model",
 * @OA\Property(property="id", type="integer", example=1),
 * @OA\Property(property="name", type="string", example="Al-Rimal"),
 * @OA\Property(property="city_id", type="integer", example=1),
 * @OA\Property(property="latitude", type="number", format="float", example=31.516),
 * @OA\Property(property="longitude", type="number", format="float", example=34.450),
 * @OA\Property(property="type", type="string", example="neighborhood"),
 * @OA\Property(
 * property="city",
 * description="City object (loaded via ?include=city)",
 * type="object",
 * example={"id": 1, "name": "Gaza"}
 * ),
 * @OA\Property(
 * property="governorate",
 * description="Governorate object (loaded via ?include=governorate)",
 * type="object",
 * example={"id": 1, "name": "Gaza Governorate"}
 * )
 * )
 */
class DistrictController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     * path="/api/v1/geolocation/districts",
     * summary="Get all Districts",
     * description="Retrieve a list of all districts with filtering, sorting, and pagination",
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
     * name="filter[city_id]", in="query", required=false, description="Filter by exact city ID",
     * @OA\Schema(type="integer")
     * ),
     * @OA\Parameter(
     * name="filter[governorate_id]", in="query", required=false, description="Filter by exact governorate ID (via city)",
     * @OA\Schema(type="integer")
     * ),
     * @OA\Parameter(
     * name="filter[type]", in="query", required=false, description="Filter by exact type",
     * @OA\Schema(type="string")
     * ),
     * @OA\Parameter(
     * name="sort",
     * in="query",
     * required=false,
     * description="Sort by fields (comma-separated). Allowed: id, name, created_at. Prepend '-' for descending.",
     * @OA\Schema(type="string", example="-created_at,name")
     * ),
     * @OA\Parameter(
     * name="include",
     * in="query",
     * required=false,
     * description="Include related resources (comma-separated). Allowed: city, governorate",
     * @OA\Schema(type="string", example="city,governorate")
     * ),
     * @OA\Parameter(
     * name="fields[districts]",
     * in="query",
     * required=false,
     * description="Select specific fields (comma-separated). Allowed: id, name, city_id, latitude, longitude, type, city, governorate",
     * @OA\Schema(type="string", example="id,name,city")
     * ),
     * @OA\Response(
     * response=200,
     * description="Districts retrieved successfully",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="message", type="string", example="Districts retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="array",
     * @OA\Items(ref="#/components/schemas/DistrictResource")
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

        $districts = QueryBuilder::for(District::class)
            ->allowedFilters(DistrictResource::allowedFilters())
            ->allowedSorts(DistrictResource::allowedSorts())
            ->allowedFields(DistrictResource::allowedFields())
            ->allowedIncludes(DistrictResource::allowedIncludes())
            ->paginate($perPage)
            ->appends(request()->query());

        return $this->successResponse(
            DistrictResource::collection($districts),
            __('Districts retrieved successfully')
        );
    }

    /**
     * @OA\Get(
     * path="/api/v1/geolocation/districts/{district}",
     * summary="Get a single district",
     * description="Retrieve details of a specific district by ID, with includes and sparse fieldsets",
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
     * name="district",
     * in="path",
     * required=true,
     * description="District ID",
     * @OA\Schema(type="integer")
     * ),
     * @OA\Parameter(
     * name="include",
     * in="query",
     * required=false,
     * description="Include related resources (comma-separated). Allowed: city, governorate",
     * @OA\Schema(type="string", example="city,governorate")
     * ),
     * @OA\Parameter(
     * name="fields[districts]",
     * in="query",
     * required=false,
     * description="Select specific fields (comma-separated). Allowed: id, name, city_id, latitude, longitude, type, city, governorate",
     * @OA\Schema(type="string", example="id,name,city")
     * ),
     * @OA\Response(
     * response=200,
     * description="District retrieved successfully",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="message", type="string", example="District retrieved successfully"),
     * @OA\Property(property="data", ref="#/components/schemas/DistrictResource")
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=404, description="District not found")
     * )
     */
    public function show($district)
    {
        $query = QueryBuilder::for(District::class)
            ->allowedFields(DistrictResource::allowedFields())
            ->allowedIncludes(DistrictResource::allowedIncludes());

        $districtInstance = $query->findOrFail($district);

        return $this->successResponse(
            new DistrictResource($districtInstance),
            __('District retrieved successfully')
        );
    }
}
