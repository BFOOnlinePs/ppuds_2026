<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Notification;
use Modules\Core\Entities\User;
use Modules\PPUDS\Entities\CompanyQrCode;
use Modules\PPUDS\Enums\InternalLoginMode;
use Modules\PPUDS\Enums\LoginMethod;
use Modules\PPUDS\Notifications\LoginOtpNotification;
use Modules\PPUDS\Services\CompanyQrCodeService;
use Tests\Support\BuildsAuthenticationTables;
use Tests\TestCase;

class OtpLoginApiTest extends TestCase
{
    use BuildsAuthenticationTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessSqliteMemory();
        $this->buildAuthenticationTables();
        $this->fakeAuthenticationSettings();

        config(['activitylog.enabled' => false]);
        Notification::fake();
    }

    private function sentCode(User $user): string
    {
        $code = null;

        Notification::assertSentTo($user, LoginOtpNotification::class, function (LoginOtpNotification $notification) use (&$code): bool {
            $code = (fn () => $this->code)->call($notification);

            return true;
        });

        return $code;
    }

    public function test_the_full_flow_returns_a_working_sanctum_token(): void
    {
        $user = $this->makeUser('supervisor@example.com', ['Company Supervisor']);

        $this->postJson('/api/v1/auth/otp/request', ['email' => 'supervisor@example.com'])
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.channel', 'mail')
            ->assertJsonPath('data.code_length', 6)
            ->assertJsonMissingPath('data.code');

        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'email' => 'supervisor@example.com',
            'code' => $this->sentCode($user),
            'device_name' => 'Pixel 9',
            'fcm_token' => 'fcm-token-1',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'supervisor@example.com');

        $this->assertDatabaseHas('device_tokens', ['user_id' => $user->id, 'token' => 'fcm-token-1', 'device_name' => 'Pixel 9']);
        $this->assertSame('Pixel 9', $user->tokens()->sole()->name);

        // The token passes the same guard stack every protected endpoint uses.
        $this->withToken($response->json('data.token'))
            ->postJson('/api/v1/auth/logout')
            ->assertOk();
    }

    public function test_a_wrong_code_is_a_422_in_the_api_envelope(): void
    {
        $this->makeUser('supervisor@example.com', ['Company Supervisor']);
        $this->postJson('/api/v1/auth/otp/request', ['email' => 'supervisor@example.com'])->assertOk();

        $this->postJson('/api/v1/auth/otp/verify', ['email' => 'supervisor@example.com', 'code' => '000000'])
            ->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', __('The code is invalid or has expired.'));
    }

    public function test_unknown_emails_get_exactly_the_same_response(): void
    {
        $this->makeUser('supervisor@example.com', ['Company Supervisor']);

        $known = $this->postJson('/api/v1/auth/otp/request', ['email' => 'supervisor@example.com'])->json();
        $unknown = $this->postJson('/api/v1/auth/otp/request', ['email' => 'nobody@example.com'])->json();

        $this->assertSame($known, $unknown);
    }

    public function test_resending_too_soon_is_a_429_with_retry_after(): void
    {
        $this->postJson('/api/v1/auth/otp/request', ['email' => 'nobody@example.com'])->assertOk();

        $this->postJson('/api/v1/auth/otp/request', ['email' => 'nobody@example.com'])
            ->assertStatus(429)
            ->assertJsonPath('status', false)
            ->assertJsonStructure(['data' => ['retry_after']]);
    }

    public function test_everything_is_off_under_the_university_login(): void
    {
        $this->fakeAuthenticationSettings([], LoginMethod::PPU);

        $this->postJson('/api/v1/auth/otp/request', ['email' => 'supervisor@example.com'])->assertForbidden();
        $this->postJson('/api/v1/auth/otp/verify', ['email' => 'supervisor@example.com', 'code' => '123456'])->assertForbidden();
        $this->postJson('/api/v1/auth/company-qr/resolve', ['qr_token' => str_repeat('a', 43)])->assertForbidden();
    }

    public function test_the_api_refuses_passwords_while_the_code_replaces_them(): void
    {
        $this->makeUser('admin@example.com', [], ['password' => bcrypt('secret-password')]);

        $this->postJson('/api/v1/auth/login', ['email' => 'admin@example.com', 'password' => 'secret-password'])
            ->assertForbidden()
            ->assertJsonPath('status', false)
            ->assertJsonMissingPath('data.token');
    }

    public function test_password_only_mode_keeps_the_existing_login_response(): void
    {
        $this->fakeAuthenticationSettings(['mobile_login_mode' => InternalLoginMode::PASSWORD]);
        $this->makeUser('admin@example.com', [], ['password' => bcrypt('secret-password')]);

        $this->postJson('/api/v1/auth/login', ['email' => 'admin@example.com', 'password' => 'secret-password'])
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonMissingPath('data.requires_code');

        $this->postJson('/api/v1/auth/otp/request', ['email' => 'admin@example.com'])->assertForbidden();
    }

    public function test_password_or_code_mode_accepts_either(): void
    {
        $this->fakeAuthenticationSettings(['mobile_login_mode' => InternalLoginMode::PASSWORD_OR_CODE]);
        $user = $this->makeUser('admin@example.com', [], ['password' => bcrypt('secret-password')]);

        $this->postJson('/api/v1/auth/login', ['email' => 'admin@example.com', 'password' => 'secret-password'])
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer');

        $this->postJson('/api/v1/auth/otp/request', ['email' => 'admin@example.com'])->assertOk();
        $this->postJson('/api/v1/auth/otp/verify', ['email' => 'admin@example.com', 'code' => $this->sentCode($user)])
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer');
    }

    public function test_password_then_code_mode_signs_in_through_the_login_ticket(): void
    {
        $this->fakeAuthenticationSettings(['mobile_login_mode' => InternalLoginMode::PASSWORD_THEN_CODE]);
        $user = $this->makeUser('admin@example.com', [], ['password' => bcrypt('secret-password')]);

        // Wrong password: the usual 401, no code sent.
        $this->postJson('/api/v1/auth/login', ['email' => 'admin@example.com', 'password' => 'wrong'])
            ->assertUnauthorized();
        Notification::assertNothingSent();

        // Right password: no token yet — a code and a ticket instead.
        $step1 = $this->postJson('/api/v1/auth/login', ['email' => 'admin@example.com', 'password' => 'secret-password'])
            ->assertOk()
            ->assertJsonPath('data.requires_code', true)
            ->assertJsonPath('data.channel', 'mail')
            ->assertJsonMissingPath('data.token');

        $ticket = $step1->json('data.login_ticket');

        // The email alone cannot ask for or use a code in this mode.
        $this->postJson('/api/v1/auth/otp/request', ['email' => 'admin@example.com'])->assertForbidden();
        $this->postJson('/api/v1/auth/otp/verify', ['email' => 'admin@example.com', 'code' => $this->sentCode($user)])->assertForbidden();

        // A forged ticket is refused.
        $this->postJson('/api/v1/auth/otp/verify', ['login_ticket' => 'forged', 'code' => $this->sentCode($user)])
            ->assertUnauthorized();

        $this->postJson('/api/v1/auth/otp/verify', ['login_ticket' => $ticket, 'code' => $this->sentCode($user), 'device_name' => 'Pixel 9'])
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.id', $user->id);
    }

    public function test_the_two_step_code_can_be_resent_with_the_ticket(): void
    {
        $this->fakeAuthenticationSettings(['mobile_login_mode' => InternalLoginMode::PASSWORD_THEN_CODE]);
        $user = $this->makeUser('admin@example.com', [], ['password' => bcrypt('secret-password')]);

        $ticket = $this->postJson('/api/v1/auth/login', ['email' => 'admin@example.com', 'password' => 'secret-password'])
            ->json('data.login_ticket');

        $this->postJson('/api/v1/auth/otp/request', ['login_ticket' => $ticket])->assertStatus(429);

        $this->travel(61)->seconds();
        Notification::fake();

        $this->postJson('/api/v1/auth/otp/request', ['login_ticket' => $ticket])
            ->assertOk()
            ->assertJsonPath('data.resend_after', 60);

        $this->postJson('/api/v1/auth/otp/verify', ['login_ticket' => $ticket, 'code' => $this->sentCode($user)])
            ->assertOk();
    }

    public function test_the_options_endpoint_reports_the_mobile_mode(): void
    {
        $this->fakeAuthenticationSettings([
            'web_login_mode' => InternalLoginMode::PASSWORD,
            'mobile_login_mode' => InternalLoginMode::PASSWORD_THEN_CODE,
        ]);

        $this->getJson('/api/v1/auth/options')
            ->assertOk()
            ->assertJsonPath('data.mode', 'password_then_code')
            ->assertJsonPath('data.password_login', true)
            ->assertJsonPath('data.code_login', false)
            ->assertJsonPath('data.two_step', true)
            ->assertJsonPath('data.code_length', 6);
    }

    public function test_the_app_config_reports_which_options_are_on(): void
    {
        \Illuminate\Support\Facades\Schema::create('app_versions', function ($table): void {
            $table->id();
            $table->string('platform');
            $table->timestamps();
        });

        $this->getJson('/api/v1/config')
            ->assertOk()
            ->assertJsonPath('data.auth.mode', 'code')
            ->assertJsonPath('data.auth.code_login', true)
            ->assertJsonPath('data.auth.company_qr_login', true)
            ->assertJsonPath('data.auth.channels', ['mail']);

        $this->fakeAuthenticationSettings([], LoginMethod::PPU);

        $this->getJson('/api/v1/config')
            ->assertJsonPath('data.auth.mode', 'password')
            ->assertJsonPath('data.auth.code_login', false)
            ->assertJsonPath('data.auth.company_qr_login', false);
    }

    // ---- Company QR ----

    private function companyWithCode(): array
    {
        $company = $this->makeCompany(name: 'Acme Training');
        $code = app(CompanyQrCodeService::class)->generate($company);

        return [$company, $code, app(CompanyQrCodeService::class)->url($code)];
    }

    public function test_a_scanned_link_resolves_to_its_company(): void
    {
        [$company, , $url] = $this->companyWithCode();

        $this->postJson('/api/v1/auth/company-qr/resolve', ['qr_token' => $url])
            ->assertOk()
            ->assertJsonPath('data.company.id', $company->id)
            ->assertJsonPath('data.company.name', 'Acme Training');
    }

    public function test_forged_revoked_and_inactive_company_codes_are_rejected(): void
    {
        [$company, $code, $url] = $this->companyWithCode();

        $this->postJson('/api/v1/auth/company-qr/resolve', ['qr_token' => str_repeat('x', 43)])->assertNotFound();
        $this->postJson('/api/v1/auth/company-qr/resolve', ['qr_token' => (string) $company->id])->assertNotFound();

        app(CompanyQrCodeService::class)->revoke($code);
        $this->postJson('/api/v1/auth/company-qr/resolve', ['qr_token' => $url])->assertNotFound();

        $fresh = app(CompanyQrCodeService::class)->generate($company);
        $company->forceFill(['status' => 0])->saveQuietly();
        $this->postJson('/api/v1/auth/company-qr/resolve', ['qr_token' => app(CompanyQrCodeService::class)->url($fresh)])->assertNotFound();
    }

    public function test_a_company_supervisor_signs_in_through_their_company_code(): void
    {
        [$company, $code, $url] = $this->companyWithCode();
        $user = $this->makeUser('supervisor@example.com', ['Company Supervisor']);
        $this->seatCompanySupervisor($company, $user);

        $this->postJson('/api/v1/auth/otp/request', ['email' => 'supervisor@example.com', 'qr_token' => $url])->assertOk();

        $this->postJson('/api/v1/auth/otp/verify', [
            'email' => 'supervisor@example.com',
            'code' => $this->sentCode($user),
            'qr_token' => $url,
        ])->assertOk()->assertJsonPath('data.user.id', $user->id);

        $this->assertSame(1, CompanyQrCode::find($code->id)->use_count);
    }

    public function test_someone_from_another_company_gets_no_code(): void
    {
        [, , $url] = $this->companyWithCode();
        $other = $this->makeCompany(name: 'Other Co');
        $user = $this->makeUser('supervisor@example.com', ['Company Supervisor']);
        $this->seatCompanySupervisor($other, $user);

        $this->postJson('/api/v1/auth/otp/request', ['email' => 'supervisor@example.com', 'qr_token' => $url])->assertOk();

        Notification::assertNothingSent();
    }

    public function test_a_role_not_allowed_for_qr_sign_in_gets_no_code(): void
    {
        [$company, , $url] = $this->companyWithCode();
        $user = $this->makeUser('supervisor@example.com', ['Company Supervisor']);
        $this->seatCompanySupervisor($company, $user);

        $this->fakeAuthenticationSettings(['qr_login_roles' => ['Student']]);

        $this->postJson('/api/v1/auth/otp/request', ['email' => 'supervisor@example.com', 'qr_token' => $url])->assertOk();

        Notification::assertNothingSent();
    }

    public function test_a_company_code_is_rejected_once_the_qr_is_revoked(): void
    {
        [$company, $code, $url] = $this->companyWithCode();
        $user = $this->makeUser('supervisor@example.com', ['Company Supervisor']);
        $this->seatCompanySupervisor($company, $user);

        $this->postJson('/api/v1/auth/otp/request', ['email' => 'supervisor@example.com', 'qr_token' => $url])->assertOk();
        $otp = $this->sentCode($user);

        app(CompanyQrCodeService::class)->revoke($code);

        $this->postJson('/api/v1/auth/otp/verify', ['email' => 'supervisor@example.com', 'code' => $otp, 'qr_token' => $url])
            ->assertNotFound();
    }
}
