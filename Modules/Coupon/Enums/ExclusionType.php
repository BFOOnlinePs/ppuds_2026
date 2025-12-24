<?php

namespace Modules\Coupon\Enums;

use Filament\Support\Contracts\HasLabel;

enum ExclusionType: int implements HasLabel
{
    case INCLUDE = 0;
    case EXCLUDE = 1;

    public function getLabel(): string
    {
        return match ($this) {
            self::INCLUDE => __('Include'),
            self::EXCLUDE => __('Exclude'),
        };
    }
}
