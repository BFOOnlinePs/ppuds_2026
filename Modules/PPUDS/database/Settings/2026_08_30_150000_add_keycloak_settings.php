<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Seeds from whatever .env currently holds, so switching the app over to
     * settings-driven configuration changes nothing on day one.
     */
    public function up(): void
    {
        $this->migrator->add('ppuds_keycloak.base_url', (string) config('services.keycloak.base_url', ''));
        $this->migrator->add('ppuds_keycloak.realm', (string) config('services.keycloak.realms', ''));
        $this->migrator->add('ppuds_keycloak.client_id', (string) config('services.keycloak.client_id', ''));
        $this->migrator->add('ppuds_keycloak.client_secret', (string) config('services.keycloak.client_secret', ''));
        $this->migrator->add('ppuds_keycloak.redirect_uri', (string) config('services.keycloak.redirect', ''));
        $this->migrator->add('ppuds_keycloak.mobile_client_id', (string) config('services.keycloak.mobile_client_id', ''));
        $this->migrator->add('ppuds_keycloak.api_client_id', (string) config('services.keycloak.api_client_id', ''));
        $this->migrator->add('ppuds_keycloak.realm_public_key', (string) config('keycloak.realm_public_key', ''));
        $this->migrator->add('ppuds_keycloak.allowed_resources', (string) config('keycloak.allowed_resources', ''));
        $this->migrator->add('ppuds_keycloak.password_grant_scope', (string) config('services.keycloak.password_grant_scope', ''));
    }

    public function down(): void
    {
        foreach ([
            'base_url', 'realm', 'client_id', 'client_secret', 'redirect_uri',
            'mobile_client_id', 'api_client_id', 'realm_public_key',
            'allowed_resources', 'password_grant_scope',
        ] as $property) {
            $this->migrator->delete("ppuds_keycloak.{$property}");
        }
    }
};
