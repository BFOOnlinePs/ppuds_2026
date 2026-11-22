<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\UserRole;
use Modules\PPUDS\Enums\InternalLoginMode;
use Modules\PPUDS\Enums\OtpChannel;
use Modules\PPUDS\Settings\AuthenticationSettings;
use Modules\PPUDS\Settings\SmsSettings;
use Modules\PPUDS\Support\Sms\SmsManager;
use Tests\TestCase;

class AuthenticationSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            $this->markTestSkipped('This test builds temporary SQLite tables and only runs against :memory:.');
        }

        Schema::dropIfExists('settings');
        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('group');
            $table->string('name');
            $table->boolean('locked')->default(false);
            $table->json('payload');
            $table->timestamps();
            $table->unique(['group', 'name']);
        });

        foreach ([
            ...glob(base_path('Modules/PPUDS/database/Settings/2026_11_19_*.php')),
            base_path('Modules/PPUDS/database/Settings/2026_11_21_000001_add_personal_qr_login_setting.php'),
            base_path('Modules/PPUDS/database/Settings/2026_11_22_000001_add_role_login_rules_setting.php'),
        ] as $migration) {
            (require $migration)->up();
        }
    }

    public function test_migrations_ship_everything_switched_off(): void
    {
        $settings = app(AuthenticationSettings::class);

        $this->assertSame(InternalLoginMode::PASSWORD, $settings->web_login_mode);
        $this->assertSame(InternalLoginMode::PASSWORD, $settings->mobile_login_mode);
        $this->assertFalse($settings->qr_login_enabled);
        $this->assertFalse($settings->personal_qr_login_enabled);
        $this->assertTrue($settings->otp_email_enabled);
        $this->assertFalse($settings->otp_sms_enabled);
        $this->assertSame(OtpChannel::MAIL, $settings->otp_default_channel);
        $this->assertSame(6, $settings->otp_length);
        $this->assertSame([UserRole::COMPANY_SUPERVISOR->value], $settings->qr_login_roles);
    }

    public function test_changes_are_persisted_and_read_back(): void
    {
        $settings = app(AuthenticationSettings::class);
        $settings->web_login_mode = InternalLoginMode::PASSWORD_OR_CODE;
        $settings->mobile_login_mode = InternalLoginMode::PASSWORD_THEN_CODE;
        $settings->otp_default_channel = OtpChannel::SMS;
        $settings->qr_login_roles = [UserRole::STUDENT->value];
        $settings->save();

        $fresh = (new AuthenticationSettings)->refresh();

        $this->assertSame(InternalLoginMode::PASSWORD_OR_CODE, $fresh->web_login_mode);
        $this->assertSame(InternalLoginMode::PASSWORD_THEN_CODE, $fresh->mobile_login_mode);
        $this->assertSame(OtpChannel::SMS, $fresh->otp_default_channel);
        $this->assertSame([UserRole::STUDENT->value], $fresh->qr_login_roles);
    }

    public function test_sms_api_key_is_encrypted_at_rest(): void
    {
        $sms = app(SmsSettings::class);
        $sms->api_key = 'super-secret-key';
        $sms->save();

        $stored = DB::table('settings')->where('group', 'ppuds_sms')->where('name', 'api_key')->value('payload');

        $this->assertStringNotContainsString('super-secret-key', $stored);
        $this->assertSame('super-secret-key', (new SmsSettings)->refresh()->api_key);
    }

    public function test_log_driver_never_counts_as_a_provider_in_production(): void
    {
        $manager = app(SmsManager::class);

        $this->assertSame(SmsManager::LOG_DRIVER, $manager->getDefaultDriver());
        $this->assertTrue($manager->canDeliver(), 'Outside production the log driver may be used.');

        $this->app->detectEnvironment(fn () => 'production');

        $this->assertFalse($manager->canDeliver());
    }

    public function test_masking_keeps_only_the_last_four_digits(): void
    {
        $this->assertSame('******4567', SmsManager::mask('059-123-4567'));
    }
}
