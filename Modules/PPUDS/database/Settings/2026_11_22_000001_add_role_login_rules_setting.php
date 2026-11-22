<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * No rules at first: everyone follows the general sign-in method until an
     * administrator sets one for a role.
     */
    public function up(): void
    {
        $this->migrator->add('ppuds_authentication.role_login_rules', []);
    }

    public function down(): void
    {
        $this->migrator->delete('ppuds_authentication.role_login_rules');
    }
};
