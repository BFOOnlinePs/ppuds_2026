<?php

namespace Modules\Items\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentStatus: int implements HasLabel, HasColor
{
    case PAID = 1;
    case UNPAID = 2;
    case REFUNDED = 3;
    case FAILED = 4;

    /**
     * لتجهيز قائمة الخيارات للاستمارات (Forms)
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn(self $case) => [$case->value => $case->getLabel()])
            ->toArray();
    }

    /**
     * لإظهار نص حالة الدفع للمستخدم
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::PAID => __('مدفوع'),
            self::UNPAID => __('غير مدفوع'),
            self::REFUNDED => __('مسترجع'),
            self::FAILED => __('فشل الدفع'),
        };
    }

    /**
     * لإعطاء لون مميز لكل حالة دفع في لوحة التحكم
     */
    public function getColor(): string | array | null
    {
        return match ($this) {
            self::PAID => 'success', // أخضر
            self::UNPAID => 'warning', // برتقالي
            self::REFUNDED => 'gray',    // رمادي
            self::FAILED => 'danger',  // أحمر
        };
    }
}
