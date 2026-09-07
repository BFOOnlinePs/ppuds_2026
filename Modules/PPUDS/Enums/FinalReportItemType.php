<?php

namespace Modules\PPUDS\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum FinalReportItemType: int implements HasLabel, HasColor
{
    case CONTRIBUTION = 1;
    case DIFFICULTY   = 2;

    public function getLabel(): ?string
    {
        return match ($this) {
            self::CONTRIBUTION => __('Key Contributions'),
            self::DIFFICULTY   => __('Training Difficulties'),
        };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::CONTRIBUTION => 'success',
            self::DIFFICULTY   => 'danger',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])
            ->toArray();
    }
}
