<?php

namespace Modules\Items\Enums;

enum CategoryStatus: int
{
    case ACTIVE = 1;
    case INACTIVE = 0;

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
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
