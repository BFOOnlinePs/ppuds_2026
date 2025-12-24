<?php

namespace Modules\Items\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: int implements HasLabel, HasColor
{
    case CASH               = 1;
    case CREDIT_CARD        = 2;
    case BANK_TRANSFER      = 3;

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
     * لإظهار اسم طريقة الدفع للمستخدم
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::CASH                      => __('الدفع عند الاستلام'),
            self::CREDIT_CARD               => __('بطاقة ائتمانية'),
            self::BANK_TRANSFER             => __('تحويل بنكي'),
        };
    }

    /**
     * لإعطاء لون مميز لكل طريقة دفع في لوحة التحكم
     */
    public function getColor(): string | array | null
    {
        return match ($this) {
            self::CASH_ON_DELIVERY => 'info',    // أزرق فاتح
            self::CREDIT_CARD      => 'primary', // اللون الأساسي
            self::PAYPAL           => 'success', // أخضر
        };
    }
}
