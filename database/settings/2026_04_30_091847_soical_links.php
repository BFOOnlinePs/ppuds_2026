<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.facebook_url', 'https://www.facebook.com/ppu.edu');
        $this->migrator->add('general.linkedin_url', 'https://www.linkedin.com/school/palestine-polytechnic-university/');
        $this->migrator->add('general.x_url', 'https://x.com/PPU_edu');
        $this->migrator->add('general.instagram_url', 'https://www.instagram.com/ppu.edu');
    }

    public function down(): void
    {
        $this->migrator->delete('general.facebook_url');
        $this->migrator->delete('general.linkedin_url');
        $this->migrator->delete('general.x_url');
        $this->migrator->delete('general.instagram_url');
    }
};
