<?php

namespace Modules\Clinic\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CustomerProgramStatus: int implements HasLabel, HasColor
{
    case ACTIVE = 1;
    case COMPLETED = 2;
    case CANCELLED = 3;

    public function getLabel(): string
    {
        return match ($this) {
            self::ACTIVE => __('Active'),
            self::COMPLETED => __('Completed'),
            self::CANCELLED => __('Cancelled'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::COMPLETED => 'primary',
            self::CANCELLED => 'danger',
        };
    }

    public function value(): int
    {
        return match ($this) {
            self::ACTIVE => 1,
            self::COMPLETED => 2,
            self::CANCELLED => 2,
        };
    }

    public static function options()
    {
        return collect(self::cases())->mapWithKeys(fn($case) => [
            $case->value => $case->getLabel(),
        ])->toArray();
    }

    public static function fromString(string $value): self
    {
        return match (strtolower($value)) {
            'active' => self::ACTIVE,
            'completed' => self::COMPLETED,
            'cancelled' => self::CANCELLED,
            default => self::ACTIVE,
        };
    }
}
