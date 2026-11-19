<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('ppuds_sms.driver', 'log');
        $this->migrator->add('ppuds_sms.api_url', '');
        $this->migrator->addEncrypted('ppuds_sms.api_key', '');
        $this->migrator->add('ppuds_sms.sender_id', '');
    }

    public function down(): void
    {
        foreach (['driver', 'api_url', 'api_key', 'sender_id'] as $property) {
            $this->migrator->delete("ppuds_sms.{$property}");
        }
    }
};
