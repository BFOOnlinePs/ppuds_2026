<?php

namespace Modules\Customer\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Status: int implements HasLabel, HasColor
{
    case ACTIVE = 1;
    case INACTIVE = 0;

    public function getLabel(): string
    {
        return match ($this) {
            self::ACTIVE => __('Active'),
            self::INACTIVE => __('Inactive'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::INACTIVE => 'danger',
        };
    }

    public function value(): int
    {
        return match ($this) {
            self::ACTIVE => 1,
            self::INACTIVE => 2,
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
            'male' => self::ACTIVE,
            'female' => self::INACTIVE,
            default => self::ACTIVE,
        };
    }
}
