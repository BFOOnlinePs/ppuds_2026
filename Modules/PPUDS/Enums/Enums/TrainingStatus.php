<?php

namespace Modules\PPUDS\Enums\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TrainingStatus: int implements HasLabel, HasColor
{
    case AVAILABLE  = 1;
    case FINISHED   = 2;
    case DELETED    = 3;

    public function getLabel(): ?string
    {
        return match ($this) {
            self::AVAILABLE => __('Available / Still Training'),
            self::FINISHED  => __('Finished'),
            self::DELETED   => __('Deleted'),
        };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::AVAILABLE => 'primary',
            self::FINISHED  => 'success',
            self::DELETED   => 'danger',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn(self $case) => [$case->value => $case->getLabel()])
            ->toArray();
    }
}
