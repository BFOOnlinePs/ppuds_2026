<?php

namespace Modules\Branch\Enums;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BranchStatus: int implements HasLabel , HasColor
{
    case OPEN = 1;
    case CLOSED = 2;


    public function getLabel(): ?string
    {
        return match ($this) {
            self::OPEN => __('Open'),
            self::CLOSED => __('Closed'),
        };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::OPEN => 'success',
            self::CLOSED => 'danger',
        };
    }
}
