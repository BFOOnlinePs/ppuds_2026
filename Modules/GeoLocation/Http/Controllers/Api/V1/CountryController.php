<?php

namespace Modules\GeoLocation\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Modules\Core\Traits\ApiResponse;
use Modules\GeoLocation\Entities\Country; // تم التغيير
use Modules\GeoLocation\Transformers\V1\CountryResource; // تم التغيير
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @OA\Schema(
 * schema="CountryResource",
 * title="Country Resource",
 * description="Country resource model",
 * @OA\Property(property="id", type="integer", example=1),
 * @OA\Property(property="name", type="string", example="Palestine"),
 * @OA\Property(property="code", type="string", example="PS"),
 * @OA\Property(property="phone_code", type="string", example="970"),
 * @OA\Property(property="currency_id", type="integer", example=1),
 * @OA\Property(
 * property="currency",
 * description="Currency object (loaded via ?include=currency)",
 * type="object",
 * example={"id": 1, "name": "Shekel", "code": "ILS"}
 * )
 * )
 */
class CountryController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     * path="/api/v1/geolocation/countries",
     * summary="Get all Countries",
     * description="Retrieve a list of all countries with filtering, sorting, and pagination",
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
     * name="filter[phone_code]", in="query", required=false, description="Filter by exact phone code",
     * @OA\Schema(type="string")
     * ),
     * @OA\Parameter(
     * name="filter[currency_id]", in="query", required=false, description="Filter by exact currency ID",
     * @OA\Schema(type="integer")
     * ),
     * @OA\Parameter(
     * name="sort",
     * in="query",
     * required=false,
     * description="Sort by fields (comma-separated). Allowed: id, name, created_at.",
     * @OA\Schema(type="string", example="-created_at,name")
     * ),
     * @OA\Parameter(
     * name="include",
     * in="query",
     * required=false,
     * description="Include related resources (comma-separated). Allowed: currency",
     * @OA\Schema(type="string", example="currency")
     * ),
     * @OA\Parameter(
     * name="fields[countries]",
     * in="query",
     * required=false,
     * description="Select specific fields (comma-separated). Allowed: id, name, code, phone_code, currency_id, currency",
     * @OA\Schema(type="string", example="id,name,code,currency")
     * ),
     * @OA\Response(
     * response=200,
     * description="Countries retrieved successfully",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="message", type="string", example="Countries retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="array",
     * @OA\Items(ref="#/components/schemas/CountryResource")
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

        $countries = QueryBuilder::for(Country::class) // تم التغيير
        ->allowedFilters(CountryResource::allowedFilters())
            ->allowedSorts(CountryResource::allowedSorts())
            ->allowedFields(CountryResource::allowedFields())
            ->allowedIncludes(CountryResource::allowedIncludes())
            ->paginate($perPage)
            ->appends(request()->query());

        return $this->successResponse(
            CountryResource::collection($countries), // تم التغيير
            __('Countries retrieved successfully') // تم التغيير
        );
    }

    /**
     * @OA\Get(
     * path="/api/v1/geolocation/countries/{country}",
     * summary="Get a single country",
     * description="Retrieve details of a specific country by ID, with includes and sparse fieldsets",
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
     * name="country",
     * in="path",
     * required=true,
     * description="Country ID",
     * @OA\Schema(type="integer")
     * ),
     * @OA\Parameter(
     * name="include",
     * in="query",
     * required=false,
     * description="Include related resources (comma-separated). Allowed: currency",
     * @OA\Schema(type="string", example="currency")
     * ),
     * @OA\Parameter(
     * name="fields[countries]",
     * in="query",
     * required=false,
     * description="Select specific fields (comma-separated). Allowed: id, name, code, phone_code, currency_id, currency",
     * @OA\Schema(type="string", example="id,name,currency")
     * ),
     * @OA\Response(
     * response=200,
     * description="Country retrieved successfully",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="message", type="string", example="Country retrieved successfully"),
     * @OA\Property(property="data", ref="#/components/schemas/CountryResource")
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=404, description="Country not found")
     * )
     */
    public function show($country) // تم التغيير
    {
        $query = QueryBuilder::for(Country::class) // تم التغيير
        ->allowedFields(CountryResource::allowedFields())
            ->allowedIncludes(CountryResource::allowedIncludes());

        $countryInstance = $query->findOrFail($country); // تم التغيير

        return $this->successResponse(
            new CountryResource($countryInstance), // تم التغيير
            __('Country retrieved successfully') // تم التغيير
        );
    }
}
