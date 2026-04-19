<?php

namespace Modules\Core\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Core\Entities\Currency;
use Modules\Core\Traits\ApiResponse;
use Modules\Core\Transformers\V1\CurrencyResource;
use Spatie\QueryBuilder\QueryBuilder;

class CurrencyController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     * path="/api/v1/currencies",
     * summary="Get all currencies",
     * tags={"Currencies"},
     * security={{"sanctum": {}}},
     * @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer")),
     * @OA\Response(response=200, description="Currencies retrieved successfully")
     * )
     */
    public function index(): JsonResponse
    {
        $defaultPerPage = config('core.pagination.per_page', 15);
        $maxPerPage = config('core.pagination.max_per_page', 100);
        $perPage = min(request('per_page', $defaultPerPage), $maxPerPage);

        $currencies = QueryBuilder::for(Currency::class)
            ->allowedFields(CurrencyResource::allowedFields())
            ->allowedSorts(CurrencyResource::allowedSorts())
            ->allowedFilters(CurrencyResource::allowedFilters())
            ->allowedIncludes(CurrencyResource::allowedIncludes())
            ->paginate($perPage)
            ->appends(request()->query());

        return $this->successResponse(
            CurrencyResource::collection($currencies),
            __('Currencies retrieved successfully')
        );
    }

    /**
     * @OA\Get(
     * path="/api/v1/currencies/{id}",
     * summary="Get a single currency",
     * tags={"Currencies"},
     * security={{"sanctum": {}}},
     * @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     * @OA\Response(response=200, description="Currency retrieved successfully")
     * )
     */
    public function show($id): JsonResponse
    {
        $currency = QueryBuilder::for(Currency::class)
            ->allowedFields(CurrencyResource::allowedFields())
            ->allowedIncludes(CurrencyResource::allowedIncludes())
            ->findOrFail($id);

        return $this->successResponse(
            new CurrencyResource($currency),
            __('Currency retrieved successfully')
        );
    }
}
