<?php

namespace Modules\GeoLocation\Enums;

enum DistrictType : int
{
    case DISTRICT = 1;
    case NEIGHBORHOOD = 2;
    case SUBURB = 3;
    case QUARTER = 4;

    public function label(): string
    {
        return match ($this) {
            self::DISTRICT => __('City'),
            self::NEIGHBORHOOD => __('Town'),
            self::SUBURB => __('Village'),
            self::QUARTER => __('Refugee Camp'),
        };
    }

    public function value(): int
    {
        return match ($this) {
            self::DISTRICT => 1,
            self::NEIGHBORHOOD => 2,
            self::SUBURB => 3,
            self::QUARTER => 4,
        };
    }

    public static function options()
    {
        return collect(self::cases())->mapWithKeys(fn($case) => [
            $case->value => $case->label()
        ])->toArray();
    }
}
