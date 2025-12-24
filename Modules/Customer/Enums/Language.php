<?php

namespace Modules\Customer\Enums;

use Filament\Support\Contracts\HasLabel;

enum Language: int implements HasLabel
{
    case AR = 1;
    case EN = 2;

    public function getLabel(): string
    {
        return match ($this) {
            self::AR => __('Arabic'),
            self::EN => __('English'),
        };
    }

    public function value(): int
    {
        return match ($this) {
            self::AR => 1,
            self::EN => 2,
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
            'arabic' => self::AR,
            'english' => self::EN,
            default => self::AR,
        };
    }
}
