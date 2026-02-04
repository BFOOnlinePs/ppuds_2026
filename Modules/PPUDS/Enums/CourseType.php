<?php

namespace Modules\PPUDS\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CourseType : int implements HasLabel, HasColor
{
    case THEORETICAL = 1;
    case PRACTICAL   = 2;
    case BOTH        = 3;

    public function getLabel(): ?string
    {
        return match ($this) {
            self::THEORETICAL => __('Theoretical'),
            self::PRACTICAL   => __('Practical'),
            self::BOTH        => __('Both (Theory & Practical)'),
            };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::THEORETICAL => 'info',
            self::PRACTICAL   => 'warning',
            self::BOTH        => 'success',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn(self $case) => [$case->value => $case->getLabel()])
            ->toArray();
    }
}
