<?php

namespace Modules\PPUDS\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CompanyStatus: int implements HasLabel, HasColor
{
    case ACTIVE     = 1;
    case INACTIVE   = 0;
    case PENDING    = 2;

    public function getLabel(): ?string
    {
        return match ($this) {
            self::ACTIVE        => __('Active'),
            self::INACTIVE      => __('Inactive'),
            self::PENDING       => __('Pending')
        };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::ACTIVE        => 'success',
            self::INACTIVE      => 'danger',
            self::PENDING       => 'warning'
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn(self $case) => [$case->value => $case->getLabel()])
            ->toArray();
    }
}
