<?php

namespace Modules\PPUDS\Support\Sms;

use Illuminate\Support\Manager;
use Modules\PPUDS\Interfaces\SmsGatewayInterface;
use Modules\PPUDS\Settings\SmsSettings;

/**
 * Picks the SMS provider the settings screen points at.
 *
 * Built on Laravel's own Manager, so a provider is one `create{Name}Driver()`
 * method here (or an `extend()` call) plus its gateway class. The driver in
 * use is read from SmsSettings on every call, so switching providers takes
 * effect without a restart.
 *
 * @method void send(string $to, string $message)
 */
class SmsManager extends Manager
{
    public const LOG_DRIVER = 'log';

    public function getDefaultDriver(): string
    {
        $driver = trim((string) $this->settings()->driver);

        return $driver !== '' ? $driver : self::LOG_DRIVER;
    }

    protected function createLogDriver(): SmsGatewayInterface
    {
        return new LogSmsGateway;
    }

    /**
     * The drivers the settings screen offers.
     *
     * @return array<string, string>
     */
    public static function driverOptions(): array
    {
        return [
            self::LOG_DRIVER => __('Log only (no texts are sent)'),
        ];
    }

    /**
     * Whether texts actually reach phones.
     *
     * The log driver sends nothing, so in production it counts as "no
     * provider" and the SMS channel stays unavailable rather than silently
     * swallowing every sign-in code. Outside production it is allowed, so the
     * flow can be exercised before a provider is chosen.
     */
    public function canDeliver(): bool
    {
        if ($this->getDefaultDriver() !== self::LOG_DRIVER) {
            return true;
        }

        return ! $this->container->isProduction();
    }

    /** `0599123456` -> `******3456`, for anything that ends up in a log. */
    public static function mask(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        $visible = substr($digits, -4);

        return str_repeat('*', max(strlen($digits) - 4, 0)).$visible;
    }

    protected function settings(): SmsSettings
    {
        return $this->container->make(SmsSettings::class);
    }
}
