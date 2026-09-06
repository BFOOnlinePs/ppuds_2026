<?php

namespace Modules\Core\Enums;

use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;

enum UserRole: string implements HasLabel, HasColor, HasIcon
{
    case SUPER_ADMIN = 'Super Admin';
    case ADMIN = 'Admin';
    case USER = 'User';
    case CUSTOMER = 'Customer';
    case STUDENT = 'Student';
    case HEAD_OF_DEPARTMENT = 'Head of Department'; // رئيس قسم
    case ADMINISTRATIVE_ASSISTANT = 'Administrative Assistant'; // مساعد إداري
    case M_AND_E_OFFICER = 'M&E Officer'; // تم حل مشكلة الرمز & هنا
    case CORPORATE_RELATIONS_OFFICER = 'Corporate Relations Officer'; // مسؤول العلاقات المؤسسية
    case PRACTICAL_TRAINING_SUPERVISOR = 'Practical Training Supervisor'; // مشرف التدريب العملي
    case COMPANY_SUPERVISOR = 'Company Supervisor'; // مشرف الشركة
    case EVALUATION_SUPERVISOR = 'Evaluation Supervisor'; // مشرف تقييم

    public function getLabel(): ?string
    {
        return match ($this) {
            self::SUPER_ADMIN => __('Super Admin'),
            self::ADMIN => __('Admin'),
            self::USER => __('User'),
            self::CUSTOMER => __('Customer'),
            self::STUDENT => __('Student'),
            self::HEAD_OF_DEPARTMENT => __('Head of Department'),
            self::ADMINISTRATIVE_ASSISTANT => __('Administrative Assistant'),
            self::M_AND_E_OFFICER => __('M&E Officer'),
            self::CORPORATE_RELATIONS_OFFICER => __('Corporate Relations Officer'),
            self::PRACTICAL_TRAINING_SUPERVISOR => __('Practical Training Supervisor'),
            self::COMPANY_SUPERVISOR => __('Company Supervisor'),
            self::EVALUATION_SUPERVISOR => __('Evaluation Supervisor'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::SUPER_ADMIN, self::ADMIN => 'danger',
            self::HEAD_OF_DEPARTMENT, self::M_AND_E_OFFICER => 'info',
            self::STUDENT => 'success',
            self::COMPANY_SUPERVISOR, self::CORPORATE_RELATIONS_OFFICER => 'warning',
            self::PRACTICAL_TRAINING_SUPERVISOR, self::EVALUATION_SUPERVISOR => 'primary',
            default => 'gray',
        };
    }

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
            self::EVALUATION_SUPERVISOR => 'heroicon-o-star',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn(self $case) => [$case->value => $case->getLabel()])
            ->toArray();
    }
}
