<?php

namespace Modules\PPUDS\Enums\Enums;

use Filament\Support\Contracts\HasLabel;

enum CvStatus: int implements HasLabel
{
    case PENDING    = 1;
    case ACCEPTED   = 2;
    case REJECTED   = 3;

    public function getLabel(): ?string
    {
        return match ($this){
            self::PENDING    => __('Pending'),
            self::ACCEPTED   => __('Accepted'),
            self::REJECTED   => __('Rejected'),
        };
    }
}
