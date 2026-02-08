<?php

namespace Modules\PPUDS\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AttendanceStatus : int implements HasLabel, HasColor
{
    case APPROVED      = 1;
    case DISCREPANCY   = 2;
    case UNDETERMINED  = 3;

    public function getLabel(): ?string
    {
        return match ($this) {
            self::APPROVED      => __('Approved'),
            self::DISCREPANCY   => __('Discrepancy'),
            self::UNDETERMINED  => __('Undetermined'),
        };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::APPROVED      => 'success',
            self::DISCREPANCY   => 'warning',
            self::UNDETERMINED  => 'gray',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn(self $case) => [$case->value => $case->getLabel()])
            ->toArray();
    }
}
