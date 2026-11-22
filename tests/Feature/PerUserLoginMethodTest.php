<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Entities\User;
use Modules\PPUDS\Entities\UserLoginPreference;
use Modules\PPUDS\Enums\InternalLoginMode;
use Modules\PPUDS\Enums\LoginClient;
use Modules\PPUDS\Enums\OtpChannel;
use Modules\PPUDS\Notifications\LoginOtpNotification;
use Modules\PPUDS\Services\LoginOtpService;
use Modules\PPUDS\Support\Sms\SmsChannel;
use Tests\Support\BuildsAuthenticationTables;
use Tests\TestCase;

/**
 * Each person signs in their own way: their own setting first, then the
 * first matching role rule, then the general setting.
 */
class PerUserLoginMethodTest extends TestCase
{
    use BuildsAuthenticationTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessSqliteMemory();
        $this->buildAuthenticationTables();

        config(['activitylog.enabled' => false]);
        Notification::fake();

        // The general method is the password; students get codes by SMS.
        $this->fakeAuthenticationSettings([
            'web_login_mode' => InternalLoginMode::PASSWORD,
            'mobile_login_mode' => InternalLoginMode::PASSWORD,
            'otp_sms_enabled' => true,
            'role_login_rules' => [
                ['role' => 'Student', 'mode' => InternalLoginMode::CODE->value, 'channel' => OtpChannel::SMS->value],
            ],
        ]);
    }

    private function service(): LoginOtpService
    {
        return app(LoginOtpService::class);
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

    private function setOwnMethod(User $user, ?InternalLoginMode $mode, ?OtpChannel $channel = null): void
    {
        UserLoginPreference::query()->updateOrCreate(['user_id' => $user->id], ['login_mode' => $mode, 'otp_channel' => $channel]);
    }

    // ---- Resolution order ----

    public function test_own_setting_beats_role_rule_beats_general_setting(): void
    {
        $student = $this->makeUser('student@example.com', ['Student']);
        $admin = $this->makeUser('admin@example.com', ['Admin']);
        $special = $this->makeUser('special@example.com', ['Student']);
        $this->setOwnMethod($special, InternalLoginMode::PASSWORD_THEN_CODE);

        $this->assertSame(InternalLoginMode::CODE, $this->service()->modeForUser($student, LoginClient::MOBILE));
        $this->assertSame(InternalLoginMode::PASSWORD, $this->service()->modeForUser($admin, LoginClient::MOBILE));
        $this->assertSame(InternalLoginMode::PASSWORD_THEN_CODE, $this->service()->modeForUser($special, LoginClient::MOBILE));
    }

    public function test_someone_with_several_roles_follows_the_first_matching_rule(): void
    {
        $this->fakeAuthenticationSettings([
            'otp_sms_enabled' => true,
            'role_login_rules' => [
                ['role' => 'Company Supervisor', 'mode' => InternalLoginMode::PASSWORD->value, 'channel' => null],
                ['role' => 'Student', 'mode' => InternalLoginMode::CODE->value, 'channel' => null],
            ],
        ]);
        $both = $this->makeUser('both@example.com', ['Student', 'Company Supervisor']);

        $this->assertSame(InternalLoginMode::PASSWORD, $this->service()->modeForUser($both, LoginClient::MOBILE));
    }

    public function test_the_channel_follows_the_person_then_the_role(): void
    {
        $student = $this->makeUser('student@example.com', ['Student'], ['phone' => '0599000001']);
        $mailStudent = $this->makeUser('mail.student@example.com', ['Student'], ['phone' => '0599000002']);
        $this->setOwnMethod($mailStudent, null, OtpChannel::MAIL);

        $this->assertSame(OtpChannel::SMS, $this->service()->channelForUser($student));
        $this->assertSame(OtpChannel::MAIL, $this->service()->channelForUser($mailStudent));
    }

    // ---- Mobile API ----

    public function test_the_options_tell_the_app_to_ask_for_the_email_first(): void
    {
        $this->getJson('/api/v1/auth/options')
            ->assertOk()
            ->assertJsonPath('data.mode', 'password')
            ->assertJsonPath('data.per_user', true)
            ->assertJsonPath('data.password_login', true)
            ->assertJsonPath('data.code_login', true);
    }

    public function test_identify_returns_each_accounts_own_method(): void
    {
        $this->makeUser('student@example.com', ['Student']);
        $this->makeUser('admin@example.com', ['Admin']);

        $this->postJson('/api/v1/auth/identify', ['email' => 'student@example.com'])
            ->assertOk()
            ->assertJsonPath('data.mode', 'code')
            ->assertJsonPath('data.default_channel', 'sms')
            ->assertJsonPath('data.channels', ['sms'])
            ->assertJsonPath('data.channel_choice', false);

        $this->postJson('/api/v1/auth/identify', ['email' => 'admin@example.com'])
            ->assertOk()
            ->assertJsonPath('data.mode', 'password');
    }

    public function test_an_unknown_email_looks_exactly_like_an_account_on_the_general_method(): void
    {
        $this->makeUser('admin@example.com', ['Admin']);

        $known = $this->postJson('/api/v1/auth/identify', ['email' => 'admin@example.com'])->json();
        $unknown = $this->postJson('/api/v1/auth/identify', ['email' => 'nobody@example.com'])->json();

        $this->assertSame($known, $unknown);
    }

    public function test_a_student_signs_in_with_the_sms_code_and_never_with_a_password(): void
    {
        $student = $this->makeUser('student@example.com', ['Student'], ['phone' => '0599000001', 'password' => bcrypt('secret-password')]);

        // The password is refused — but only after it was found right.
        $this->postJson('/api/v1/auth/login', ['email' => 'student@example.com', 'password' => 'wrong'])->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', ['email' => 'student@example.com', 'password' => 'secret-password'])
            ->assertForbidden()
            ->assertJsonMissingPath('data.token');

        $this->postJson('/api/v1/auth/otp/request', ['email' => 'student@example.com'])
            ->assertOk()
            ->assertJsonPath('data.channel', 'sms');

        Notification::assertSentTo($student, LoginOtpNotification::class, fn ($n, array $channels) => $channels === [SmsChannel::class]);

        $this->postJson('/api/v1/auth/otp/verify', ['email' => 'student@example.com', 'code' => $this->sentCode($student)])
            ->assertOk()
            ->assertJsonPath('data.user.id', $student->id);
    }

    public function test_a_password_account_gets_no_code_but_the_same_answer(): void
    {
        $this->makeUser('admin@example.com', ['Admin'], ['password' => bcrypt('secret-password')]);

        $this->postJson('/api/v1/auth/otp/request', ['email' => 'admin@example.com'])->assertOk();
        Notification::assertNothingSent();

        $this->postJson('/api/v1/auth/login', ['email' => 'admin@example.com', 'password' => 'secret-password'])
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer');
    }

    public function test_a_person_set_to_two_step_gets_the_second_step(): void
    {
        $user = $this->makeUser('admin@example.com', ['Admin'], ['password' => bcrypt('secret-password')]);
        $this->setOwnMethod($user, InternalLoginMode::PASSWORD_THEN_CODE);

        $ticket = $this->postJson('/api/v1/auth/login', ['email' => 'admin@example.com', 'password' => 'secret-password'])
            ->assertOk()
            ->assertJsonPath('data.requires_code', true)
            ->json('data.login_ticket');

        $this->postJson('/api/v1/auth/otp/verify', ['login_ticket' => $ticket, 'code' => $this->sentCode($user)])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id);
    }

    public function test_changing_someones_method_applies_at_once_even_to_a_code_already_sent(): void
    {
        $student = $this->makeUser('student@example.com', ['Student'], ['phone' => '0599000001']);
        $this->postJson('/api/v1/auth/otp/request', ['email' => 'student@example.com'])->assertOk();
        $code = $this->sentCode($student);

        $this->setOwnMethod($student, InternalLoginMode::PASSWORD);

        $this->postJson('/api/v1/auth/otp/verify', ['email' => 'student@example.com', 'code' => $code])->assertStatus(422);
    }

    public function test_without_any_own_or_role_method_nothing_changes(): void
    {
        $this->fakeAuthenticationSettings(['mobile_login_mode' => InternalLoginMode::PASSWORD]);

        $this->getJson('/api/v1/auth/options')
            ->assertJsonPath('data.per_user', false)
            ->assertJsonPath('data.code_login', false);

        $this->postJson('/api/v1/auth/otp/request', ['email' => 'anyone@example.com'])->assertForbidden();
    }

    // ---- Dashboard (web) ----

    private function prepareWebPages(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('group');
            $table->string('name');
            $table->boolean('locked')->default(false);
            $table->json('payload');
            $table->timestamps();
        });
        DB::table('settings')->insert(['group' => 'general', 'name' => 'site_name', 'payload' => json_encode('PPUDS')]);

        if (! Route::has('home')) {
            Route::get('/__home', fn () => 'home')->name('home');
        }
    }

    public function test_the_dashboard_offers_both_doors_and_sends_each_person_through_theirs(): void
    {
        $this->prepareWebPages();
        $this->makeUser('student@example.com', ['Student'], ['password' => bcrypt('secret-password')]);
        $admin = $this->makeUser('admin@example.com', ['Admin'], ['password' => bcrypt('secret-password')]);

        // Password page, with the way to the code page beside it.
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('name="password"', false)
            ->assertSee(route('login.otp'));

        // A student's right password is turned away to the code page.
        $this->post(route('login.store'), ['email' => 'student@example.com', 'password' => 'secret-password'])
            ->assertRedirect(route('login.otp'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        // Someone on the general password method signs in as always.
        $this->post(route('login.store'), ['email' => 'admin@example.com', 'password' => 'secret-password']);
        $this->assertAuthenticatedAs($admin);
    }
}
