<?php

namespace Modules\PPUDS\Enums;

use Filament\Support\Contracts\HasLabel;

enum StudentGender: int implements HasLabel
{
    case MALE           = 0;
    case FEMALE         = 1;

    public function getLabel(): ?string
    {
        return match ($this){
            self::MALE    => __('Male'),
            self::FEMALE   => __('Female'),
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn(self $case) => [$case->value => $case->getLabel()])
            ->toArray();
    }
}
