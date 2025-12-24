<?php

namespace Modules\Delivery\Services;

use MatanYadaev\EloquentSpatial\Objects\Point;
use Modules\Core\Exceptions\BusinessRuleException;
use Modules\Delivery\Entities\DeliveryZone;

class DeliveryFeeCalculatorService
{
    public function handle() {

    }

    private function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusKm = 6371;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $lat1 = deg2rad($lat1);
        $lat2 = deg2rad($lat2);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            sin($dLon / 2) * sin($dLon / 2) * cos($lat1) * cos($lat2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        // المسافة محسوبة بالكيلومتر
        return $earthRadiusKm * $c;
    }

    public function calculate(float $customerLat, float $customerLng, int $branchId): array
    {
        // 💡 استخدم 0 كما اتفقنا لحل مشكلة SRID
        $customerPoint = new Point($customerLat, $customerLng, 4326);

        // 1. إيجاد المنطقة
        $zone = DeliveryZone::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->whereContains('zone_area', $customerPoint)
            ->with([
                'deliveryPricing.deliveryFeeTiers',
                'branch'
            ])
            ->first();

        // 2. التحقق إذا كان خارج النطاق
        if (!$zone) {
            throw new BusinessRuleException(__('This location is outside our delivery zones.'), 404);
        }

        // 3. التحقق من إعدادات الفرع والتسعير
        $branch = $zone->branch;
        $pricingModel = $zone->deliveryPricing;

        if (!$branch || !$pricingModel || !$branch->latitude || !$branch->longitude) {
            throw new BusinessRuleException(__('Branch or pricing configuration error.'), 500);
        }

        // 4. 💡💡 حساب المسافة (باستخدام الدالة الجديدة) 💡💡
        $distanceInKm = $this->haversineDistance(
            (float) $branch->latitude,
            (float) $branch->longitude,
            $customerLat,
            $customerLng
        );

        // 5. حساب السعر
        $totalPrice = $pricingModel->base_fee + ($distanceInKm * $pricingModel->price_per_km);

        $applicableTier = $pricingModel->deliveryFeeTiers
            ->where('min_distance_km', '<=', $distanceInKm)
            ->sortByDesc('min_distance_km')
            ->first();

        if ($applicableTier) {
            $totalPrice += $applicableTier->extra_charge;
        }

        // 6. إرجاع النتيجة
        return [
            'total_fee' => round($totalPrice),
            'distance_km' => round($distanceInKm),
            'zone_name' => $zone->name,
            'pricing_model' => [
                'base_fee' => (float) $pricingModel->base_fee,
                'price_per_km' => (float) $pricingModel->price_per_km,
                'applied_tier_charge' => $applicableTier ? (float) $applicableTier->extra_charge : 0,
            ]
        ];
    }
}
