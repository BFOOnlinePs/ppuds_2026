<?php

namespace Modules\PPUDS\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SurveyQuestionType : int implements HasLabel, HasColor
{
    case TEXT           = 1;
    case TEXTAREA       = 2;
    case RADIO          = 3;
    case CHECKBOX       = 4;
    case SELECT         = 5;
    case DATE           = 6;

    public function getLabel(): ?string
    {
        return match ($this) {
            self::TEXT      => __('Text'),
            self::TEXTAREA   => __('Textarea'),
            self::RADIO  => __('Radio'),
            self::CHECKBOX  => __('Checkbox'),
            self::SELECT     => __('Select'),
            self::DATE       => __('Date'),
        };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::TEXT          => 'primary',
            self::TEXTAREA      => 'primary',
            self::RADIO         => 'success',
            self::CHECKBOX      => 'success',
            self::SELECT        => 'warning',
            self::DATE          => 'warning',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn(self $case) => [$case->value => $case->getLabel()])
            ->toArray();
    }
}
