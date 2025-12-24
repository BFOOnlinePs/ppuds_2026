<?php

namespace Modules\Marketing\Enums;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

// Change from :int to :string
enum LoyaltyRuleType: string implements HasLabel , HasColor
{
    // Change integer values to string values
    case BASE_RATE = 'base_rate';
    case FIXED_BONUS = 'fixed_bonus';


    public function getLabel(): ?string
    {
        return match ($this) {
            self::BASE_RATE => __('Base Rate'),
            self::FIXED_BONUS => __('Fixed Bonus'),
        };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::BASE_RATE => 'primary',
            self::FIXED_BONUS => 'primary',
        };
    }
}
