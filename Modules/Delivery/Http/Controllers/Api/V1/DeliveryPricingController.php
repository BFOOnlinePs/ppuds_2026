<?php

namespace Modules\Delivery\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Core\Traits\ApiResponse;
use Modules\Delivery\Entities\DeliveryPricing;
use Modules\Delivery\Transformers\V1\DeliveryPricingResource;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Delivery Pricing
 *
 * API endpoints for managing delivery pricings.
 */
class DeliveryPricingController extends Controller
{
    use ApiResponse;

    /**
     * Get a paginated list of delivery pricings.
     *
     * @OA\Get(
     *     path="/api/v1/delivery/pricings",
     *     summary="Get delivery pricings",
     *     tags={"Delivery"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=false,
     *         description="Language header (ar or en)",
     *         @OA\Schema(type="string", default="ar", example="en")
     *     ),
     *     @OA\Parameter(name="include", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="fields", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", example=10)),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(
     *         response=200,
     *         description="Delivery pricings retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Delivery pricings retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Standard Pricing"),
     *                     @OA\Property(property="base_fee", type="number", format="float", example=5.00),
     *                     @OA\Property(property="price_per_km", type="number", format="float", example=0.50),
     *                     @OA\Property(property="description", type="string", example="Basic delivery charges"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-11-04T10:00:00Z")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index()
    {
        $defaultPerPage = config('core.pagination.per_page', 15);
        $maxPerPage = config('core.pagination.max_per_page', 100);
        $perPage = min((int) request('per_page', $defaultPerPage), $maxPerPage);

        $pricings = QueryBuilder::for(DeliveryPricing::class)
            ->allowedFields(DeliveryPricingResource::allowedFields())
            ->allowedFilters(DeliveryPricingResource::allowedFilters())
            ->allowedSorts(DeliveryPricingResource::allowedSorts())
            ->allowedIncludes(DeliveryPricingResource::allowedIncludes())
            ->with(['deliveryFeeTiers'])
            ->paginate($perPage)
            ->appends(request()->query());

        return $this->successResponse(
            DeliveryPricingResource::collection($pricings),
            __('Delivery pricings retrieved successfully')
        );
    }

    /**
     * Get a single delivery pricing.
     *
     * @OA\Get(
     *     path="/api/v1/delivery/pricings/{id}",
     *     summary="Get a delivery pricing",
     *     tags={"Delivery"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(response=200, description="Delivery pricing retrieved successfully"),
     *     @OA\Response(response=404, description="Delivery pricing not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function show(DeliveryPricing $pricing)
    {
        if (method_exists($pricing, 'load')) {
            $pricing->load(['translations', 'deliveryFeeTiers']);
        }

        return $this->successResponse(
            new DeliveryPricingResource($pricing),
            __('Delivery pricing retrieved successfully')
        );
    }
}
