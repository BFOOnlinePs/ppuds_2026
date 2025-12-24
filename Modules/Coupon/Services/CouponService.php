<?php

namespace Modules\Coupon\Services;

use App\Models\User;
use Modules\Coupon\Entities\Coupon;
use Exception;
use Google\Protobuf\Api;
use Illuminate\Support\Facades\DB;
use Modules\Core\Exceptions\BusinessRuleException;
use Modules\Core\Traits\ApiResponse;
use Modules\Coupon\Enums\CouponType;
use Modules\Coupon\Interfaces\Discountable;

class CouponService
{
    use ApiResponse;

    public function handle() {}

    public function apply(string $code, iterable $cartItems)
    {
        $coupon = Coupon::where('code', $code)->first();

        $this->validate($coupon, auth()->user() ?? null);

        $restrictions = $coupon->couponables;
        $isGlobal = $restrictions->isEmpty();

        $discountableAmount = 0;

        foreach ($cartItems as $item) {

            if (empty($item) || empty($item->model)) {
                continue;
            }

            $model = $item->model;

            if (! $model instanceof Discountable) {
                continue;
            }

            if ($isGlobal) {
                $discountableAmount += $model->getDiscountablePrice() * $item->qty;
                continue;
            }

            /** @var \Illuminate\Database\Eloquent\Model|\Modules\Coupon\Interfaces\Discountable $model */
            $modelType = $model->getMorphClass();
            $modelId = $model->getKey();

            $isListed = $restrictions->contains(function ($r) use ($modelId, $modelType) {
                return $r->couponable_id == $modelId && $r->couponable_type === $modelType;
            });

            $type = $restrictions->first()->exclusion_type;

            if (($type === 'include' && $isListed) || ($type === 'exclude' && !$isListed)) {
                $discountableAmount += $model->getDiscountablePrice() * $item->qty;
            }
        }

        if ($discountableAmount <= 0) {
            throw new BusinessRuleException(__('This Coupon is not applicable to the selected items.'));
        }

        $this->validateMinOrderAmount($coupon, $discountableAmount);

        $discountValue = match ($coupon->type) {
            CouponType::PERCENTAGE => ($discountableAmount * $coupon->value) / 100,

            CouponType::FIXED => min($coupon->value, $discountableAmount),

            default => 0,
        };

        return [
            'valid' => true,
            'code' => $coupon->code,
            'discount_amount' => $discountValue,
            'new_total' => $discountableAmount - $discountValue // (تقريبي حسب منطق السلة لديك)
        ];
    }

    public function logUsage(string $code, int $orderId, float $discountAmount, ?int $userId = null)
    {
        DB::transaction(function () use ($code, $orderId, $discountAmount, $userId) {
            $coupon = Coupon::where('code', $code)->lockForUpdate()->first();

            $this->validate($coupon, auth()->user() ?? null);

            if (!$coupon) {
                throw new BusinessRuleException(__('Invalid Coupon.'));
            }

            $coupon->current_uses += 1;
            $coupon->save();

            $coupon->usages()->create([
                'order_id' => $orderId,
                'user_id' => $userId,
                'discount_amount' => $discountAmount,
            ]);
        });
    }

    protected function validate(?Coupon $coupon, $user = null)
    {
        if (!$coupon || !$coupon->is_active) {
            throw new BusinessRuleException(__('Invalid Coupon.'));
        }

        if ($coupon->starts_at && now()->lt($coupon->starts_at)) {
            throw new BusinessRuleException(__('This Coupon is not yet valid.'));
        }

        if ($coupon->expires_at && now()->gt($coupon->expires_at)) {
            throw new BusinessRuleException(__('This Coupon has expired.'));
        }

        if ($coupon->max_uses && $coupon->current_uses >= $coupon->max_uses) {
            throw new BusinessRuleException(__('This Coupon has reached its maximum number of uses.'));
        }

        if ($user) {
            $hasUsedBefore = $coupon->usages()
                ->where('user_id', $user->id)
                ->exists();

            if ($hasUsedBefore) {
                throw new BusinessRuleException(__('You have already used this Coupon.'));
            }
        }
    }

    public function validateMinOrderAmount(Coupon $coupon, float $amount)
    {
        if ($coupon->min_order_amount > 0 && $amount < $coupon->min_order_amount) {
            throw new BusinessRuleException(__('The minimum amount for this Coupon is :amount.', ['amount' => $coupon->min_order_amount]));
        }
    }
}
