<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('ppuds_keycloak.mobile_client_secret', (string) config('services.keycloak.mobile_client_secret', ''));
    }

    public function down(): void
    {
        $this->migrator->delete('ppuds_keycloak.mobile_client_secret');
    }
};
