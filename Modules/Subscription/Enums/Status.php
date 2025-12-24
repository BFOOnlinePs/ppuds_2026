<?php

namespace Modules\Subscription\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Status: int implements HasLabel, HasColor
{
    case ACTIVE = 1;
    case EXPIRED = 2;

    case CANCELLED = 3;

    case FROZEN = 4;

    public function getLabel(): string
    {
        return match ($this) {
            self::ACTIVE => __('Active'),
            self::EXPIRED => __('Expired'),
            self::CANCELLED => __('Cancelled'),
            self::FROZEN => __('Frozen'),
        };
    }

    public function getColor(): string | array
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::EXPIRED => 'danger',
            self::CANCELLED => 'danger',
            self::FROZEN => 'primary',
        };
    }

    public function value(): int
    {
        return match ($this) {
            self::ACTIVE => 1,
            self::EXPIRED => 2,
            self::CANCELLED => 3,
            self::CANCELLED => 4,
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
            'expired' => self::EXPIRED,
            'cancelled' => self::CANCELLED,
            'frozen' => self::FROZEN,
            default => self::ACTIVE,
        };
    }
}
