<?php

namespace Modules\Core\Enums;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TransactionType: string implements HasLabel, HasColor
{
    case Refund = 'Refund';                       // إرجاع مبالغ للعملاء
    case PrepaymentDeposit = 'PrepaymentDeposit';   // دفعة مقدمة/إضافة رصيد (عام لكن يمكن تخصيصه)
    case OtherIncome = 'OtherIncome';             // دخل متنوع غير مصنف
    case GeneralExpense = 'GeneralExpense';       // مصروفات عامة

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Refund => 'إرجاع مالي',
            self::PrepaymentDeposit => 'دفعة مقدمة / رصيد',
            self::OtherIncome => 'دخل متنوع',
            self::GeneralExpense => 'مصروف عام',
        };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::Refund => Color::Red,
            self::GeneralExpense => Color::Orange,

            self::PrepaymentDeposit => Color::Blue,
            self::OtherIncome => Color::Green,
        };

        // أو يمكنك استخدام أسماء الألوان النصية: 'danger', 'warning', 'primary', 'success'
        /*
        return match ($this) {
            self::Refund => 'danger',
            self::GeneralExpense => 'warning',
            self::PrepaymentDeposit => 'info',
            self::OtherIncome => 'success',
        };
        */
    }
}
