<?php

namespace Modules\PPUDS\Enums\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum LeaveRequestStatus : int implements HasLabel, HasColor
{
    case APPROVED = 1;
    case REJECTED = 2;

    public function getLabel(): ?string
    {
        return match ($this) {
            self::APPROVED        => __('Approved'),
            self::REJECTED      => __('Rejected'),
        };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::APPROVED        => 'success',
            self::REJECTED      => 'danger',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn(self $case) => [$case->value => $case->getLabel()])
            ->toArray();
    }
}
