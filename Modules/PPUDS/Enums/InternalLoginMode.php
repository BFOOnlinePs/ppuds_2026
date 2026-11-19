<?php

namespace Modules\PPUDS\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * How people sign in through the internal login (never the university SSO).
 * Chosen separately for the dashboard and for the mobile app.
 */
enum InternalLoginMode: int implements HasColor, HasDescription, HasLabel
{
    // Email and password, as the system always worked.
    case PASSWORD = 1;

    // Email, then the one-time code sent to it. No password anywhere.
    case CODE = 2;

    // Each person picks: the password, or the code.
    case PASSWORD_OR_CODE = 3;

    // Email and password, then a one-time code to confirm (two-step).
    case PASSWORD_THEN_CODE = 4;

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PASSWORD => __('Password only'),
            self::CODE => __('One-time code only (no password)'),
            self::PASSWORD_OR_CODE => __('Password or one-time code (user chooses)'),
            self::PASSWORD_THEN_CODE => __('Password, then one-time code (two-step)'),
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::PASSWORD => __('Email and password, without any code.'),
            self::CODE => __('The person enters their email, then the code sent to them. Nobody enters a password.'),
            self::PASSWORD_OR_CODE => __('The sign-in screen offers both: the password, or a code sent to the email.'),
            self::PASSWORD_THEN_CODE => __('After a correct password, a code is sent and must be entered to finish signing in.'),
        };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::PASSWORD => 'gray',
            self::CODE => 'success',
            self::PASSWORD_OR_CODE => 'info',
            self::PASSWORD_THEN_CODE => 'warning',
        };
    }

    /** The key the API and the mobile app use for this mode. */
    public function key(): string
    {
        return match ($this) {
            self::PASSWORD => 'password',
            self::CODE => 'code',
            self::PASSWORD_OR_CODE => 'password_or_code',
            self::PASSWORD_THEN_CODE => 'password_then_code',
        };
    }

    public function allowsPassword(): bool
    {
        return $this !== self::CODE;
    }

    /** A code on its own, without any password, signs the person in. */
    public function allowsCodeOnly(): bool
    {
        return $this === self::CODE || $this === self::PASSWORD_OR_CODE;
    }

    public function requiresCodeAfterPassword(): bool
    {
        return $this === self::PASSWORD_THEN_CODE;
    }

    public function usesCodes(): bool
    {
        return $this !== self::PASSWORD;
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])
            ->toArray();
    }
}
