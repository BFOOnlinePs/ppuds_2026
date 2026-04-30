<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('ppuds_general.facebook_url', 'https://www.facebook.com/ppu.edu');
        $this->migrator->add('ppuds_general.linkedin_url', 'https://www.linkedin.com/school/palestine-polytechnic-university/');
        $this->migrator->add('ppuds_general.x_url', 'https://x.com/PPU_edu');
        $this->migrator->add('ppuds_general.instagram_url', 'https://www.instagram.com/ppu.edu');
    }

    public function down(): void
    {
        $this->migrator->delete('ppuds_general.facebook_url');
        $this->migrator->delete('ppuds_general.linkedin_url');
        $this->migrator->delete('ppuds_general.x_url');
        $this->migrator->delete('ppuds_general.instagram_url');
    }
};
