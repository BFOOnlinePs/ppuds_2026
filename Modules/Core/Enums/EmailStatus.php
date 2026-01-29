<?php

namespace Modules\Core\Enums;

use Filament\Support\Contracts\HasLabel;

enum EmailStatus: int implements HasLabel
{
    case PENDING    = 0;
    case SENT       = 1;
    case FAILED     = 2;

    public function getLabel(): ?string
    {
        return match ($this) {
            static::PENDING    => __('Pending'),
            static::SENT       => __('Sent'),
            static::FAILED     => __('Failed'),
        };
    }
}
