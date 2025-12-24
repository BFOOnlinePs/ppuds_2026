<?php

namespace Modules\Customer\Enums;

use Filament\Support\Contracts\HasLabel;

enum GenderType: int implements HasLabel
{
    case MALE = 1;
    case FEMALE = 2;

    public function getLabel(): string
    {
        return match ($this) {
            self::MALE => __('Male'),
            self::FEMALE => __('Female'),
        };
    }

    public function value(): int
    {
        return match ($this) {
            self::MALE => 1,
            self::FEMALE => 2,
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
            'male' => self::MALE,
            'female' => self::FEMALE,
            default => self::MALE,
        };
    }
}
