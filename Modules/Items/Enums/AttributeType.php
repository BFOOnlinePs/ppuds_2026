<?php

namespace Modules\Items\Enums;

enum AttributeType: int
{
    case RADIO          = 1;
    case SELECT         = 2;
    case COLOR          = 3;
    case IMAGE          = 4;

    /*************  ✨ Windsurf Command ⭐  *************/
    /**
     * Get an array of all attribute types.
     *
     * @return array An array of all attribute types.
     */

    /*******  f184c952-a5ab-46b5-9f7d-90b1db4126f9  *******/
    public static function array(): array
    {
        return [
            self::SELECT,
            self::COLOR,
            self::IMAGE,
            self::RADIO
        ];
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn(self $case) => [$case->value => $case->label()])
            ->toArray();
    }

    public function label(): string
    {
        return match ($this) {
            self::SELECT         => __('Select'),
            self::COLOR          => __('Color'),
            self::IMAGE          => __('Image'),
            self::RADIO          => __('Radio'),
        };
    }
}
