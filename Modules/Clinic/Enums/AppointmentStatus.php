<?php

namespace Modules\Clinic\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AppointmentStatus: int implements HasLabel, HasColor
{
    case PENDING = 1;
    case UNDER_EXAMINATION = 2;
    case CANCELLED = 3;
    case COMPLETED = 4;



    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => __('Pending'),
            self::UNDER_EXAMINATION => __('Under Examination'),
            self::COMPLETED => __('Completed'),
            self::CANCELLED => __('Cancelled'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::UNDER_EXAMINATION => 'success',
            self::COMPLETED => 'primary',
            self::CANCELLED => 'danger',
        };
    }

    public function value(): int
    {
        return match ($this) {
            self::PENDING => 1,
            self::UNDER_EXAMINATION => 2,
            self::COMPLETED => 3,
            self::CANCELLED => 4,
        };
    }

    public static function options()
    {
        return collect(self::cases())->mapWithKeys(fn($case) => [
            $case->value => $case->getLabel(),
        ])->toArray();
    }
}
