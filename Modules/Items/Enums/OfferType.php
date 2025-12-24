<?php

namespace Modules\Items\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OfferType: int implements HasLabel, HasColor
{
    case PERCENTAGE = 1;
    case FIXED = 2;
    case FREE_SHIPPING = 3;

    public function getLabel(): string
    {
        return match ($this) {
            self::PERCENTAGE => __('Percentage'),
            self::FIXED => __('Fixed'),
            self::FREE_SHIPPING => __('Free Shipping'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PERCENTAGE => 'primary',
            self::FIXED => 'primary',
            self::FREE_SHIPPING => 'success',
        };
    }

    public function value(): int
    {
        return match ($this) {
            self::PERCENTAGE => 1,
            self::FIXED => 2,
            self::FREE_SHIPPING => 3,
        };
    }

    public static function options()
    {
        return collect(self::cases())->mapWithKeys(fn($case) => [
            $case->value => $case->getLabel(),
        ])->toArray();
    }
}
