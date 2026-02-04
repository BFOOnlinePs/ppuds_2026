<?php

namespace Modules\PPUDS\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ReportStatus : int implements HasLabel, HasColor
{
    case OPEN   = 1;
    case CLOSED = 2;

    public function getLabel(): ?string
    {
        return match ($this) {
            self::OPEN   => __('Open'),
            self::CLOSED => __('Closed'),
        };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::OPEN   => 'success',
            self::CLOSED => 'danger',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn(self $case) => [$case->value => $case->getLabel()])
            ->toArray();
    }
}
