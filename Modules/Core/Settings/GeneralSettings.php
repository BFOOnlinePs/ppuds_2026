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

    public string $facebook_url = 'https://www.facebook.com/ppu.edu';
    public string $linkedin_url = 'https://www.linkedin.com/school/palestine-polytechnic-university/';
    public string $x_url = 'https://x.com/PPU_edu';
    public string $instagram_url = 'https://www.instagram.com/ppu.edu';

    public static function group(): string
    {
        return 'general';
    }
}
