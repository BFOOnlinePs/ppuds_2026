<?php

namespace Modules\GeoLocation\Enums;

enum CapitalType : int
{
    case COUNTRY = 1;
    case GOVERNORATE = 2;
    case BOTH = 3;

    public function label(): string
    {
        return match ($this) {
            self::COUNTRY => __('Currency'),
            self::GOVERNORATE => __('Governorate'),
            self::BOTH => __('Both'),
        };
    }

    public function value(): int
    {
        return match ($this) {
            self::COUNTRY => 1,
            self::GOVERNORATE => 2,
            self::BOTH => 3,
        };
    }

    public static function options()
    {
        return collect(self::cases())->mapWithKeys(fn($case) => [
            $case->value => $case->label()
        ])->toArray();
    }
}
