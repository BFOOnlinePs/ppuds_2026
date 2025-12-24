<?php

namespace Modules\Core\Settings;

use Spatie\LaravelSettings\Settings;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class GeneralSettings extends Settings
{
    public string $site_name = 'Be Found Online';
    public string $email_address_for_contact = 'contact@bfo.com';
    public string $site_description = 'Be Found Online Description';
    public ?string $site_logo_url = null;

    public static function group(): string
    {
        return 'general';
    }
}
