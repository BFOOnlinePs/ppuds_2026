<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.site_name', 'Be Found Online');
        $this->migrator->add('general.email_address_for_contact', 'contact@be_found_online.com');
        $this->migrator->add('general.site_description', 'Be Found Online');
        $this->migrator->add('general.site_logo_url', '');
        // $this->migrator->add('core.general.favicon', 'default-favicon');
    }
};
