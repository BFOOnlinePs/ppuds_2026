<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('ppuds_general.start_semester', '2026-01-01');
        $this->migrator->add('ppuds_general.end_semester', '2027-01-01');
    }

    public function down(): void
    {
        $this->migrator->delete('ppuds_general.start_semester');
        $this->migrator->delete('ppuds_general.end_semester');
    }
};
