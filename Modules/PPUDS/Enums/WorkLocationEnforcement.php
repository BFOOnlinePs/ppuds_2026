<?php

namespace Modules\PPUDS\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * How strictly students must be standing at their training branch when they
 * check in or out.
 */
enum WorkLocationEnforcement: int implements HasColor, HasLabel
{
    /** No location check at all. */
    case DISABLED = 1;

    /** Every student must be inside the branch radius. */
    case ALL_MAJORS = 2;

    /** Only students of the majors listed in the settings must be. */
    case SELECTED_MAJORS = 3;

    public function getLabel(): ?string
    {
        return match ($this) {
            self::DISABLED => __('Not Required'),
            self::ALL_MAJORS => __('Required For All Majors'),
            self::SELECTED_MAJORS => __('Required For Selected Majors Only'),
        };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::DISABLED => 'gray',
            self::ALL_MAJORS => 'success',
            self::SELECTED_MAJORS => 'warning',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])
            ->toArray();
    }
}
