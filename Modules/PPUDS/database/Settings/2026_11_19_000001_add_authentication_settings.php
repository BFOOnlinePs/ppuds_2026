<?php

use Modules\Core\Enums\UserRole;
use Modules\PPUDS\Enums\InternalLoginMode;
use Modules\PPUDS\Enums\OtpChannel;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Everything starts switched off, so deploying this changes nothing until
     * an administrator turns it on from the settings screen.
     */
    public function up(): void
    {
        // Password only, exactly as before, until an administrator changes it.
        $this->migrator->add('ppuds_authentication.web_login_mode', InternalLoginMode::PASSWORD->value);
        $this->migrator->add('ppuds_authentication.mobile_login_mode', InternalLoginMode::PASSWORD->value);

        $this->migrator->add('ppuds_authentication.qr_login_enabled', false);
        $this->migrator->add('ppuds_authentication.qr_login_roles', [
            UserRole::COMPANY_SUPERVISOR->value,
        ]);
        $this->migrator->add('ppuds_authentication.qr_code_validity_days', 0);

        $this->migrator->add('ppuds_authentication.otp_length', 6);
        $this->migrator->add('ppuds_authentication.otp_ttl_minutes', 10);
        $this->migrator->add('ppuds_authentication.otp_max_attempts', 5);
        $this->migrator->add('ppuds_authentication.otp_resend_cooldown_seconds', 60);
        $this->migrator->add('ppuds_authentication.otp_max_sends_per_hour', 5);

        $this->migrator->add('ppuds_authentication.otp_email_enabled', true);
        $this->migrator->add('ppuds_authentication.otp_sms_enabled', false);
        $this->migrator->add('ppuds_authentication.otp_default_channel', OtpChannel::MAIL->value);
        $this->migrator->add('ppuds_authentication.otp_allow_channel_choice', false);
        $this->migrator->add('ppuds_authentication.otp_sms_fallback_to_email', true);
        $this->migrator->add('ppuds_authentication.sms_daily_limit', 500);
    }

    public function down(): void
    {
        foreach ([
            'web_login_mode', 'mobile_login_mode',
            'qr_login_enabled', 'qr_login_roles', 'qr_code_validity_days',
            'otp_length', 'otp_ttl_minutes', 'otp_max_attempts',
            'otp_resend_cooldown_seconds', 'otp_max_sends_per_hour',
            'otp_email_enabled', 'otp_sms_enabled', 'otp_default_channel',
            'otp_allow_channel_choice', 'otp_sms_fallback_to_email', 'sms_daily_limit',
        ] as $property) {
            $this->migrator->delete("ppuds_authentication.{$property}");
        }
    }
};
