<?php

namespace Modules\PPUDS\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SurveyQuestionType: int implements HasLabel, HasColor
{
    case TEXT           = 1; // إجابة نصية قصيرة
    case TEXTAREA       = 2; // إجابة نصية طويلة
    case RADIO          = 3; // خيار واحد (دواءر)
    case CHECKBOX       = 4; // عدة خيارات (مربعات)
    case SELECT         = 5; // قائمة منسدلة (خيار واحد)
    case MULTI_SELECT   = 6; // قائمة منسدلة (عدة خيارات) - تم إضافته
    case DATE           = 7; // تاريخ
    case FILE           = 8; // رفع ملف
    case RATING         = 9; // تقييم (نجوم)

    public function getLabel(): ?string
    {
        return match ($this) {
            self::TEXT          => __('Short Text'),
            self::TEXTAREA      => __('Long Text'),
            self::RADIO         => __('Single Choice (Radio)'),
            self::CHECKBOX      => __('Multiple Choice (Checkbox)'),
            self::SELECT        => __('Dropdown (Single)'),
            self::MULTI_SELECT  => __('Dropdown (Multiple)'),
            self::DATE          => __('Date Picker'),
            self::FILE          => __('File Upload'),
            self::RATING        => __('Star Rating'),
        };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::TEXT, self::TEXTAREA  => 'gray',
            self::RADIO, self::CHECKBOX => 'success',
            self::SELECT, self::MULTI_SELECT => 'info',
            self::DATE                  => 'warning',
            self::FILE                  => 'primary',
            self::RATING                => 'danger',
        };
    }

    // دالة مساعدة لتحديد هل النوع يحتاج لتعريف خيارات أم لا
    public function hasOptions(): bool
    {
        return in_array($this, [
            self::RADIO, 
            self::CHECKBOX, 
            self::SELECT, 
            self::MULTI_SELECT
        ]);
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn(self $case) => [$case->value => $case->getLabel()])
            ->toArray();
    }
}