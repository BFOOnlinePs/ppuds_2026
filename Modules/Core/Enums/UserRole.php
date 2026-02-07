<?php

namespace Modules\Core\Enums;

use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;

enum UserRole: string implements HasLabel, HasColor, HasIcon
{
    // --- 1. System Roles (أدوار النظام الأساسية) ---
    case SUPER_ADMIN = 'Super Admin';
    case ADMIN = 'Admin';
    case USER = 'User';
    case CUSTOMER = 'Customer';

    // --- 2. Students (الطلاب) ---
    case STUDENT = 'Student';

    // --- 3. PPUDS Staff (الطاقم الأكاديمي والإداري) ---
    // هنا قمت بإصلاح الأسماء البرمجية لتكون صالحة (بدون مسافات)
    // مع الحفاظ على القيمة النصية كما طلبتها بالضبط في الداتابيز
    case HEAD_OF_DEPARTMENT = 'Head of Department';
    case ADMINISTRATIVE_ASSISTANT = 'Administrative Assistant';
    case M_AND_E_OFFICER = 'M&E Officer'; // تم حل مشكلة الرمز & هنا
    case CORPORATE_RELATIONS_OFFICER = 'Corporate Relations Officer';
    case PRACTICAL_TRAINING_SUPERVISOR = 'Practical Training Supervisor';

    // --- 4. Companies (الشركات) ---
    case COMPANY_SUPERVISOR = 'Company Supervisor';

    /**
     * النص الظاهر في الواجهة (Label)
     */
    public function getLabel(): ?string
    {
        // بما أن القيم المخزنة هي نفسها النصوص المقروءة، نعيد القيمة مباشرة
        return $this->value;
    }

    /**
     * الألوان (Colors)
     * تظهر تلقائياً في الجداول والـ Badges
     */
    public function getColor(): string|array|null
    {
        return match ($this) {
            // الإدارة العليا (أحمر)
            self::SUPER_ADMIN, self::ADMIN => 'danger',

            // رؤساء الأقسام والمسؤولين (أزرق)
            self::HEAD_OF_DEPARTMENT, self::M_AND_E_OFFICER => 'info',

            // الطلاب (أخضر)
            self::STUDENT => 'success',

            // الشركات (برتقالي/أصفر)
            self::COMPANY_SUPERVISOR, self::CORPORATE_RELATIONS_OFFICER => 'warning',

            // المشرفين والموظفين الآخرين (رمادي أو أزرق فاتح)
            self::PRACTICAL_TRAINING_SUPERVISOR => 'primary',
            default => 'gray',
        };
    }

    /**
     * الأيقونات (Icons)
     * تعطي طابعاً احترافياً جداً في القوائم
     */
    public function getIcon(): ?string
    {
        return match ($this) {
            self::SUPER_ADMIN, self::ADMIN => 'heroicon-o-shield-check',
            self::USER, self::CUSTOMER => 'heroicon-o-user',
            self::STUDENT => 'heroicon-o-academic-cap',
            self::HEAD_OF_DEPARTMENT => 'heroicon-o-building-library',
            self::ADMINISTRATIVE_ASSISTANT => 'heroicon-o-clipboard-document-list',
            self::M_AND_E_OFFICER => 'heroicon-o-chart-bar-square',
            self::CORPORATE_RELATIONS_OFFICER => 'heroicon-o-briefcase',
            self::PRACTICAL_TRAINING_SUPERVISOR => 'heroicon-o-user-group',
            self::COMPANY_SUPERVISOR => 'heroicon-o-building-office',
        };
    }
}
