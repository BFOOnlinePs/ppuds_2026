<?php

namespace Modules\Coupon\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Modules\Core\Traits\ApiResponse;
use Modules\Coupon\Entities\Coupon;
use Modules\Coupon\Transformers\V1\CouponeResource;
use Modules\Delivery\Entities\CustomerAddress;
use Modules\Delivery\Http\Requests\CustomerAddressesRequest;
use Modules\Delivery\Services\DeliveryFeeCalculatorService;
use Modules\Delivery\Transformers\V1\CustomerAdressesResource;
use Modules\Delivery\Transformers\V1\DeliveryPricingResource;
use Spatie\QueryBuilder\QueryBuilder;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Coupon\Services\CouponService;
use Modules\Items\Entities\Product;

/**
 * @group Customer Addresses
 *
 * API endpoints for managing customer addresses.
 */
class CouponsController extends Controller
{
    use ApiResponse;

    protected $couponService;

    public function __construct(CouponService $couponService)
    {
        $this->couponService = $couponService;
    }

    /**
     * List coupons.
     *
     * @OA\Get(
     *     path="/api/v1/coupon/coupons",
     *     summary="Get coupons list",
     *     tags={"Coupons"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=false,
     *         description="Language header (ar or en)",
     *         @OA\Schema(type="string", default="ar", example="en")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Coupons retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Coupons retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="code", type="string", example="WELCOME10"),
     *                     @OA\Property(property="type", type="string", example="percentage"),
     *                     @OA\Property(property="value", type="number", format="float", example=10),
     *                     @OA\Property(property="max_usage", type="integer", example=100),
     *                     @OA\Property(property="expires_at", type="string", format="date-time", example="2025-12-31T23:59:59Z")
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

        $coupons = QueryBuilder::for(Coupon::class)
            ->allowedFields(CouponeResource::allowedFields())
            ->allowedFilters(CouponeResource::allowedFilters())
            ->allowedSorts(CouponeResource::allowedSorts())
            ->allowedIncludes(CouponeResource::allowedIncludes())
            ->paginate($perPage)
            ->appends(request()->query());

        return $this->successResponse(
            CouponeResource::collection($coupons),
            __('Coupons retrieved successfully')
        );
    }

    public function store(Coupon $request) {}

    /**
     * Get a single copon.
     *
     * @OA\Get(
     * path="/api/v1/coupon/coupons/{coupon}",
     * summary="Get a single coupon",
     * tags={"Coupons"},
     * security={{"sanctum": {}}},
     * @OA\Parameter(
     * name="coupon",
     * in="path",
     * required=true,
     * @OA\Schema(type="integer", example=1)
     * ),
     * @OA\Parameter(name="include", in="query", required=false, @OA\Schema(type="string", example="user")),
     * @OA\Response(
     * response=200,
     * description="Customer address retrieved successfully",
     * @OA\JsonContent(
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Customer address retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="object",
     * @OA\Property(property="id", type="integer", example=1),
     * @OA\Property(property="user_id", type="integer", example=1),
     * @OA\Property(property="label", type="string", example="Home"),
     * @OA\Property(property="latitude", type="number", format="float", example=31.50000000),
     * @OA\Property(property="longitude", type="number", format="float", example=34.46667000),
     * @OA\Property(property="address_details", type="string", example="Building 1, Floor 2, Apt 5"),
     * @OA\Property(property="delivery_instructions", type="string", example="Call upon arrival"),
     * @OA\Property(property="is_default", type="boolean", example=true)
     * )
     * )
     * ),
     * @OA\Response(response=404, description="Customer address not found"),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function show(Coupon $coupon)
    {
        return $this->successResponse(
            new CouponeResource($coupon),
            __('Coupon retrieved successfully')
        );
    }

    /**
     * Apply a coupon.
     *
     * @OA\Post(
     *     path="/api/v1/coupon/coupons/apply",
     *     summary="Apply a coupon",
     *     tags={"Coupons"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"code"},
     *             @OA\Property(property="code", type="string", example="WELCOME10"),
     *             @OA\Property(
     *                 property="items",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="qty", type="integer", example=2)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Coupon applied successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Coupon applied successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="discount_amount", type="number", format="float", example=15.00),
     *                 @OA\Property(property="new_total", type="number", format="float", example=85.00)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation Error"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */

    public function apply(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'items' => 'array|sometimes',
            'items.*.id' => 'sometimes|integer',
            'items.*.type' => 'sometimes|in:product',
            'items.*.qty' => 'sometimes|integer|min:1',
        ]);

        $validItems = [];

        foreach ($request->items as $itemData) {
            $cartEntry = new \stdClass();
            $cartEntry->qty = $itemData['qty'];
            $cartEntry->model = null;

            if ($itemData['type'] === 'product') {
                $cartEntry->model = Product::find($itemData['id']);
            }

            if ($cartEntry->model) {
                $validItems[] = $cartEntry;
            }
        }

        if (empty($validItems)) {
            return $this->errorResponse('لم يتم العثور على المنتجات المطلوبة', 422);
        }

        try {
            $result = $this->couponService->apply($request->code, $validItems);

            return $this->successResponse($result, __('Coupon applied successfully'));
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function update(CustomerAddressesRequest $request, CustomerAddress $customerAddresses) {}

    public function destroy($id) {}
}
