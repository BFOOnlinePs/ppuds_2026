<?php

namespace Modules\Clinic\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum QuestionType: int implements HasLabel, HasColor
{
    case TEXTAREA = 1;
    case CHECKBOX = 2;
    case RADIO = 3;
    case TEXT = 4;

    public function getLabel(): string
    {
        return match ($this) {
            self::TEXTAREA => __('Textarea'),
            self::CHECKBOX => __('Checkbox'),
            self::RADIO => __('Radio'),
            self::TEXT => __('Text'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::TEXTAREA => 'primary',
            self::CHECKBOX => 'primary',
            self::RADIO => 'primary',
            self::TEXT => 'primary',
        };
    }

    public function value(): int
    {
        return match ($this) {
            self::TEXTAREA => 1,
            self::CHECKBOX => 2,
            self::RADIO => 3,
            self::TEXT => 4,
        };
    }

    public static function is(mixed $value, self $type): bool
    {
        return $value === $type->value;
    }

    public static function options()
    {
        return collect(self::cases())->mapWithKeys(fn($case) => [
            $case->value => $case->getLabel(),
        ])->toArray();
    }
}
