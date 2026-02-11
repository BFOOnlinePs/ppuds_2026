<?php

namespace Modules\PPUDS\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentStatus : int implements HasLabel, HasColor
{
    case UNPAID  = 1;
    case PAID    = 2;

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PAID   => __('Paid'),
            self::UNPAID => __('Unpaid'),
        };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::PAID   => 'success',
            self::UNPAID => 'danger',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn(self $case) => [$case->value => $case->getLabel()])
            ->toArray();
    }
}
