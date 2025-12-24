<?php

namespace Modules\Delivery\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Objects\Polygon;
use Modules\Core\Traits\ApiResponse;
use Modules\Delivery\Entities\DeliveryZone;
use Modules\Delivery\Services\DeliveryFeeCalculatorService;
use Throwable;

/**
 * @group Delivery
 *
 * API endpoints for delivery calculations.
 */
class DeliveryCalculationController extends Controller
{
    // 1. استخدم الـ Trait الخاص بك
    use ApiResponse;

    // 2. احقن (Inject) الـ Service
    protected $calculatorService;

    public function __construct(DeliveryFeeCalculatorService $calculatorService)
    {
        $this->calculatorService = $calculatorService;
    }

    /**
     * Calculate delivery fee
     *
     * @OA\Post(
     * path="/api/v1/delivery/calculate-fee",
     * summary="Calculate delivery fee",
     * tags={"Delivery"},
     * security={{"sanctum": {}}},
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"latitude", "longitude", "branch_id"},
     * @OA\Property(property="latitude", type="number", format="float", example="31.5000"),
     * @OA\Property(property="longitude", type="number", format="float", example="34.4667"),
     * @OA\Property(property="branch_id", type="integer", example=1)
     * )
     * ),
     * @OA\Response(response=200, description="Fee calculated successfully"),
     * @OA\Response(response=404, description="Location outside delivery zones"),
     * @OA\Response(response=422, description="Validation error")
     * )
     */
    public function __invoke(Request $request)
    {
        // 3. التحقق من المدخلات
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'branch_id' => ['required', 'integer', 'exists:branch_branches,id'],
        ]);

        try {

            // 4. استدعاء الـ Service بالبيانات النظيفة
            $result = $this->calculatorService->calculate(
                $data['latitude'],
                $data['longitude'],
                $data['branch_id']
            );

            // 5. إرجاع الرد الناجح
            return $this->successResponse(
                $result,
                __('Delivery fee calculated successfully.')
            );

        } catch (Throwable $e) {
            // 6. التقاط أي خطأ (مثل "خارج النطاق")
            $code = $e->getCode() >= 400 ? $e->getCode() : 422;
            return $e->getMessage();
            return $this->errorResponse($e->getMessage(), $code);
        }
    }
}
