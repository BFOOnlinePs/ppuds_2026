<?php

namespace Tests\Feature;

use Modules\Core\Livewire\Pages\Settings\Index;
use Modules\PPUDS\Enums\InternalLoginMode;
use Modules\PPUDS\Enums\OtpChannel;
use Spatie\Permission\Models\Role;
use Tests\Support\BuildsAuthenticationTables;
use Tests\TestCase;

/**
 * The settings page's "how each role will sign in" table must tell the same
 * story as the real sign-in rules.
 */
class LoginSettingsSummaryTest extends TestCase
{
    use BuildsAuthenticationTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessSqliteMemory();
        $this->buildAuthenticationTables();

        foreach (['Admin', 'Student', 'Company Supervisor'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    private function summary(array $state): string
    {
        $get = fn (string $key) => $state[$key] ?? null;

        return (fn () => $this->loginSummary($get)->toHtml())->call(new Index);
    }

    /** The table row of one role, as plain text. */
    private function row(string $html, string $roleLabel): string
    {
        preg_match_all('/<tr>(.*?)<\/tr>/s', $html, $rows);

        foreach ($rows[1] as $row) {
            $text = trim(preg_replace('/\s+/', ' ', strip_tags($row)));

            if (str_starts_with($text, __($roleLabel))) {
                return $text;
            }
        }

        $this->fail("No row for {$roleLabel}");
    }

    public function test_students_on_sms_codes_and_supervisors_on_personal_qr(): void
    {
        $html = $this->summary([
            'auth_web_login_mode' => InternalLoginMode::PASSWORD->value,
            'auth_mobile_login_mode' => InternalLoginMode::PASSWORD->value,
            'auth_otp_email_enabled' => true,
            'auth_otp_sms_enabled' => true,
            'auth_otp_default_channel' => OtpChannel::MAIL->value,
            'auth_role_login_rules' => [
                ['role' => 'Student', 'mode' => InternalLoginMode::CODE->value, 'channel' => OtpChannel::SMS->value],
            ],
            'auth_personal_qr_login_enabled' => true,
            'auth_qr_login_enabled' => false,
            'auth_qr_login_roles' => ['Company Supervisor'],
        ]);

        $student = $this->row($html, 'Student');
        $this->assertStringContainsString(__('Code only'), $student);
        $this->assertStringContainsString(__('SMS'), $student);
        $this->assertStringContainsString(__('exception'), $student);

        $supervisor = $this->row($html, 'Company Supervisor');
        $this->assertStringContainsString(__('Password'), $supervisor);
        $this->assertStringContainsString(__('Personal code: straight in'), $supervisor);

        $admin = $this->row($html, 'Admin');
        $this->assertStringContainsString(__('Password'), $admin);
        $this->assertStringNotContainsString(__('Personal code: straight in'), $admin);
    }

    public function test_code_methods_read_as_the_password_while_no_channel_is_on(): void
    {
        $html = $this->summary([
            'auth_web_login_mode' => InternalLoginMode::CODE->value,
            'auth_mobile_login_mode' => InternalLoginMode::CODE->value,
            'auth_otp_email_enabled' => false,
            'auth_otp_sms_enabled' => false,
            'auth_role_login_rules' => [],
        ]);

        $this->assertStringContainsString(e(__('No delivery channel is on, so every method that needs a code falls back to the password. Turn on email or SMS under "Sending Codes".')), $html);
        $this->assertStringNotContainsString(__('Code only'), $this->row($html, 'Admin'));
    }
}
