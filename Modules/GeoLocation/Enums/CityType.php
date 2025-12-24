<?php

namespace Modules\GeoLocation\Enums;

enum CityType : int
{
    case CITY = 1;
    case TOWN = 2;
    case VILLAGE = 3;

    case REFUGEE_CAMP = 4;

    public function label(): string
    {
        return match ($this) {
            self::CITY => __('City'),
            self::TOWN => __('Town'),
            self::VILLAGE => __('Village'),
            self::REFUGEE_CAMP => __('Refugee Camp'),
        };
    }

    public function value(): int
    {
        return match ($this) {
            self::CITY => 1,
            self::TOWN => 2,
            self::VILLAGE => 3,
            self::REFUGEE_CAMP => 4,
        };
    }

    public static function options()
    {
        return collect(self::cases())->mapWithKeys(fn($case) => [
            $case->value => $case->label()
        ])->toArray();
    }

    public static function fromString(string $value): self
    {
        return match (strtolower($value)) {
            'city' => self::CITY,
            'town' => self::TOWN,
            'village' => self::VILLAGE,
            'refugee_camp' => self::REFUGEE_CAMP,
            default => self::CITY,
        };
    }
}
