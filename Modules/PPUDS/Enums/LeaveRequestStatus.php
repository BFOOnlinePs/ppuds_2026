<?php

namespace Modules\PPUDS\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum LeaveRequestStatus: int implements HasLabel, HasColor
{
    case APPROVED   = 1;
    case REJECTED   = 2;
    case PENDING    = 3;

    public function getLabel(): ?string
    {
        return match ($this) {
            self::APPROVED      => __('Approved'),
            self::REJECTED      => __('Rejected'),
            self::PENDING       => __('Pending')
        };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::APPROVED          => 'success',
            self::REJECTED          => 'danger',
            self::PENDING           => 'warning'
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn(self $case) => [$case->value => $case->getLabel()])
            ->toArray();
    }
}
