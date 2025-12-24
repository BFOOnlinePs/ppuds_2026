<?php

namespace Modules\Clinic\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethod : int implements HasLabel
{
    case CASH           = 1;
    case CREDIT_CARD    = 2;
    case BANK_TRANSFER  = 3;

    public function getLabel(): string
    {
        return match($this) {
            self::CASH => __('Cash'),
            self::CREDIT_CARD => __('Credit Card'),
            self::BANK_TRANSFER => __('Bank Transfer'),
        };
    }

    public static function options()
    {
        return collect(self::cases())->mapWithKeys(fn($case) => [
            $case->value => $case->getLabel(),
        ])->toArray();
    }
}
