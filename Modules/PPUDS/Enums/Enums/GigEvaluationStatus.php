<?php

namespace Modules\PPUDS\Enums\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum GigEvaluationStatus : int implements HasLabel, HasColor
{
    case ACTIVE     = 1;
    case NOT_ACTIVE = 2;

    public function getLabel(): ?string
    {
        return match ($this) {
            self::ACTIVE     => __('Active'),
            self::NOT_ACTIVE => __('Not Active'),
        };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::ACTIVE     => 'success',
            self::NOT_ACTIVE => 'danger',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn(self $case) => [$case->value => $case->getLabel()])
            ->toArray();
    }
}
