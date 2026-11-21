<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Off by default: personal QR codes sign people in without any code, so
     * an administrator has to switch them on deliberately.
     */
    public function up(): void
    {
        $this->migrator->add('ppuds_authentication.personal_qr_login_enabled', false);
    }

    public function down(): void
    {
        $this->migrator->delete('ppuds_authentication.personal_qr_login_enabled');
    }
};
