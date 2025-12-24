<?php

namespace Modules\Coupon\Enums;

use Filament\Support\Contracts\HasLabel;

enum CouponType: int implements HasLabel
{
    case PERCENTAGE = 1;
    case FIXED = 2;

    public function getLabel(): string
    {
        return match ($this) {
            self::PERCENTAGE => __('Percentage'),
            self::FIXED => __('Fixed'),
        };
    }

    public static function options()
    {
        return collect(self::cases())->mapWithKeys(fn($case) => [
            $case->value => $case->getLabel(),
        ])->toArray();
    }
}
