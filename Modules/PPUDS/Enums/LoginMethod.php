<?php

namespace Modules\PPUDS\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum LoginMethod : int implements HasLabel, HasColor
{
    case SYSTEM = 1; // النظام المحلي
    case PPU    = 2; // نظام الجامعة

    public function getLabel(): ?string
    {
        return match ($this) {
            self::SYSTEM => __('Internal System'),
            self::PPU    => __('University SSO (PPU)'),
        };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::SYSTEM => 'info',
            self::PPU    => 'success',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn(self $case) => [$case->value => $case->getLabel()])
            ->toArray();
    }
}
