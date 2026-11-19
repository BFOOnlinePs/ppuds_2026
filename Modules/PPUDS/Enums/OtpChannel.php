<?php

namespace Modules\PPUDS\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * How a sign-in code reaches the user.
 */
enum OtpChannel: int implements HasColor, HasLabel
{
    case MAIL = 1;
    case SMS = 2;

    public function getLabel(): ?string
    {
        return match ($this) {
            self::MAIL => __('Email'),
            self::SMS => __('SMS'),
        };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::MAIL => 'info',
            self::SMS => 'success',
        };
    }

    /** The key the mobile app and the API use for this channel. */
    public function key(): string
    {
        return match ($this) {
            self::MAIL => 'mail',
            self::SMS => 'sms',
        };
    }

    public static function fromKey(?string $key): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->key() === $key) {
                return $case;
            }
        }

        return null;
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])
            ->toArray();
    }
}
