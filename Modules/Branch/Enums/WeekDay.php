<?php

namespace Modules\Branch\Enums;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum WeekDay: int implements HasLabel , HasColor
{
    case SATURDAY       = 1;
    case SUNDAY         = 2;
    case MONDAY         = 3;
    case TUESDAY        = 4;
    case WEDNESDAY      = 5;
    case THURSDAY       = 6;
    case FRIDAY         = 7;


    public function getLabel(): ?string
    {
        return match ($this) {
            self::SATURDAY      => __('Saturday'),
            self::SUNDAY        => __('Sunday'),
            self::MONDAY        => __('Monday'),
            self::TUESDAY       => __('Tuesday'),
            self::WEDNESDAY     => __('Wednesday'),
            self::THURSDAY      => __('Thursday'),
            self::FRIDAY        => __('Friday'),
        };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::SATURDAY, self::SUNDAY, self::MONDAY, self::TUESDAY, self::WEDNESDAY, self::THURSDAY, self::FRIDAY => "primary",
        };
    }
}
