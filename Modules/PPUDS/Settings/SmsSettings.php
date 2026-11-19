<?php

namespace Modules\PPUDS\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * The SMS gateway sign-in codes are texted through.
 *
 * The api key is stored encrypted, and the settings screen never sends it to
 * the browser: leaving the field empty keeps the stored value.
 *
 * NOTE: no docblocks on the properties, see GeneralSettings.
 */
class SmsSettings extends Settings
{
    // Which SmsManager driver sends the texts. "log" only writes them to the log.
    public string $driver;

    // The gateway endpoint.
    public string $api_url;

    // The gateway credential. Encrypted at rest.
    public string $api_key;

    // The sender name the gateway shows to the recipient.
    public string $sender_id;

    public static function group(): string
    {
        return 'ppuds_sms';
    }

    public static function encrypted(): array
    {
        return ['api_key'];
    }
}
