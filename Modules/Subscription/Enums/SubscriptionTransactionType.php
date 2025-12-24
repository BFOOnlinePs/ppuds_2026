<?php

namespace Modules\Subscription\Enums;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SubscriptionTransactionType: string implements HasLabel, HasColor
{
    case Renewal = 'SubscriptionRenewal';
    case NewSubscription = 'NewSubscription';
    case CancellationFee = 'CancellationFee';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Renewal => 'Renewal',
            self::NewSubscription => 'NewSubscription',
            self::CancellationFee => 'CancellationFee',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Renewal => 'success',
            self::NewSubscription => 'info',
            self::CancellationFee => 'danger',
        };
    }
}
