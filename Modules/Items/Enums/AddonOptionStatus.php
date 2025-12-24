<?php

namespace Modules\Items\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AddonOptionStatus: int implements HasLabel, HasColor
{
    case ACTIVE = 1;
    case INACTIVE = 0;

    public function getLabel(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::INACTIVE => 'danger',
        };
    }

    public function value(): int
    {
        return match ($this) {
            self::ACTIVE => 1,
            self::INACTIVE => 0,
        };
    }
}
