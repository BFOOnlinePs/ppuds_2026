<?php

namespace Modules\PPUDS\Enums\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon; // إضافة مفيدة للعرض
use Filament\Support\Contracts\HasLabel;

enum LeaveRequestType: int implements HasLabel, HasColor, HasIcon
{
    case ANNUAL         = 1;
    case SICK           = 2;
    case REMOTE_WORK    = 3;
    case ABROAD         = 4;
    case WORK_FROM_HOME = 5;
    case BUSINESS_TRIP  = 6;

    public function getLabel(): ?string
    {
        return match ($this) {
            self::ANNUAL         => __('Annual Leave'),
            self::SICK           => __('Sick Leave'),
            self::REMOTE_WORK    => __('Remote Work'),
            self::ABROAD         => __('Travel Abroad'),
            self::WORK_FROM_HOME => __('Work From Home'),
            self::BUSINESS_TRIP  => __('Business Trip'),
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::ANNUAL         => 'info',    // أزرق: إجازة عادية
            self::SICK           => 'danger',  // أحمر: مرضي
            self::REMOTE_WORK    => 'success', // أخضر: عمل (لكن عن بعد)
            self::WORK_FROM_HOME => 'success', // أخضر
            self::ABROAD         => 'warning', // برتقالي: سفر
            self::BUSINESS_TRIP  => 'primary', // نيلي: مهمة عمل رسمية
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::ANNUAL         => 'heroicon-m-calendar',
            self::SICK           => 'heroicon-m-face-frown',
            self::REMOTE_WORK    => 'heroicon-m-computer-desktop',
            self::WORK_FROM_HOME => 'heroicon-m-home',
            self::ABROAD         => 'heroicon-m-globe-alt',
            self::BUSINESS_TRIP  => 'heroicon-m-briefcase',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn(self $case) => [$case->value => $case->getLabel()])
            ->toArray();
    }
}
