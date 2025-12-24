<?php

namespace Modules\Reels\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ReelStatus: int implements HasLabel, HasColor
{
    case PENDING = 1;
    case APPROVED = 2;
    case REJECTED = 3;

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
        };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
        };
    }
}
