<?php

namespace Modules\Subscription\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: int implements HasLabel
{
    case CASH = 1;
    case CARD = 2;

    case TRANSFER = 3;

    public function getLabel(): string
    {
        return match ($this) {
            self::CASH => __('Cash'),
            self::CARD => __('Card'),
            self::TRANSFER => __('Transfer'),
        };
    }

    public function value(): int
    {
        return match ($this) {
            self::CASH => 1,
            self::CARD => 2,
            self::TRANSFER => 3,
        };
    }

    public static function options()
    {
        return collect(self::cases())->mapWithKeys(fn($case) => [
            $case->value => $case->getLabel(),
        ])->toArray();
    }

    public static function fromString(string $value): self
    {
        return match (strtolower($value)) {
            'cash' => self::CASH,
            'card' => self::CARD,
            'transfer' => self::TRANSFER,
            default => self::CASH,
        };
    }
}
