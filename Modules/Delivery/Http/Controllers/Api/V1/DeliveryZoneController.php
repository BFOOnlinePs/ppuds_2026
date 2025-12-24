<?php

namespace Modules\Delivery\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Core\Traits\ApiResponse;
use Modules\Delivery\Entities\DeliveryZone;
use Modules\Delivery\Transformers\V1\DeliveryZoneResource;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Delivery Zones
 *
 * API endpoints for managing delivery zones.
 */
class DeliveryZoneController extends Controller
{
    use ApiResponse;

    /**
     * Get a paginated list of delivery zones.
     *
     * @OA\Get(
     *     path="/api/v1/delivery/zones",
     *     summary="Get delivery zones",
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
     *         description="Delivery zones retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Delivery zones retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Zone A"),
     *                     @OA\Property(property="zone_area", type="string", example="POLYGON((...))"),
     *                     @OA\Property(property="delivery_pricing_id", type="integer", example=2),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
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

        $zones = QueryBuilder::for(DeliveryZone::class)
            ->allowedFields(DeliveryZoneResource::allowedFields())
            ->allowedFilters(DeliveryZoneResource::allowedFilters())
            ->allowedSorts(DeliveryZoneResource::allowedSorts())
            ->allowedIncludes(DeliveryZoneResource::allowedIncludes())
            ->with(['deliveryPricing.deliveryFeeTiers'])
            ->paginate($perPage)
            ->appends(request()->query());

        return $this->successResponse(
            DeliveryZoneResource::collection($zones),
            __('Delivery zones retrieved successfully')
        );
    }

    /**
     * Get a single delivery zone.
     *
     * @OA\Get(
     *     path="/api/v1/delivery/zones/{id}",
     *     summary="Get a delivery zone",
     *     tags={"Delivery"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(response=200, description="Delivery zone retrieved successfully"),
     *     @OA\Response(response=404, description="Delivery zone not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function show(DeliveryZone $zone)
    {
        if (method_exists($zone, 'load')) {
            $zone->load(['translations', 'deliveryPricing.deliveryFeeTiers', 'branch']);
        }

        return $this->successResponse(
            new DeliveryZoneResource($zone),
            __('Delivery zone retrieved successfully')
        );
    }
}
