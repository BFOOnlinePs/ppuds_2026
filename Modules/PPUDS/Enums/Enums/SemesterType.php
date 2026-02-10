<?php

namespace Modules\PPUDS\Enums\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SemesterType : int implements HasLabel, HasColor
{
    case FIRST  = 1;
    case SECOND = 2;
    case SUMMER = 3;

    public function getLabel(): ?string
    {
        return match ($this) {
            self::FIRST  => __('First Semester'),
            self::SECOND => __('Second Semester'),
            self::SUMMER => __('Summer Semester'),
        };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::FIRST  => 'info',
            self::SECOND => 'info',
            self::SUMMER => 'warning',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn(self $case) => [$case->value => $case->getLabel()])
            ->toArray();
    }
}
