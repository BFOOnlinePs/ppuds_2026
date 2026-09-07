<?php

namespace Modules\PPUDS\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum FinalReportStatus: int implements HasLabel, HasColor
{
    case DRAFT     = 1;
    case SUBMITTED = 2;

    public function getLabel(): ?string
    {
        return match ($this) {
            self::DRAFT     => __('Draft'),
            self::SUBMITTED => __('Submitted'),
        };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::DRAFT     => 'warning',
            self::SUBMITTED => 'success',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])
            ->toArray();
    }
}
