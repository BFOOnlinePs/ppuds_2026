<?php

namespace Tests\Unit;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Modules\Core\Entities\User;
use Modules\PPUDS\Entities\LoginOtp;
use Modules\PPUDS\Enums\InternalLoginMode;
use Modules\PPUDS\Enums\LoginClient;
use Modules\PPUDS\Enums\LoginMethod;
use Modules\PPUDS\Enums\OtpChannel;
use Modules\PPUDS\Exceptions\OtpLoginException;
use Modules\PPUDS\Notifications\LoginOtpNotification;
use Modules\PPUDS\Services\LoginOtpService;
use Tests\Support\BuildsAuthenticationTables;
use Tests\TestCase;

class LoginOtpServiceTest extends TestCase
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

    private function service(): LoginOtpService
    {
        return app(LoginOtpService::class);
    }

    /** The code a fake notification carried, read the way the mail would print it. */
    private function sentCode(User $user): string
    {
        $code = null;

        Notification::assertSentTo($user, LoginOtpNotification::class, function (LoginOtpNotification $notification) use (&$code): bool {
            $code = (fn () => $this->code)->call($notification);

            return true;
        });

        return $code;
    }

    public function test_a_code_is_generated_hashed_and_sent_by_email(): void
    {
        $user = $this->makeUser('supervisor@example.com', ['Company Supervisor']);

        $challenge = $this->service()->request('supervisor@example.com', null, null, null, LoginClient::MOBILE);

        $this->assertSame(OtpChannel::MAIL, $challenge->channel);
        $this->assertSame(600, $challenge->expiresIn);

        $code = $this->sentCode($user);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        $otp = LoginOtp::sole();
        $this->assertNotSame($code, $otp->code_hash);
        $this->assertStringNotContainsString($code, json_encode(DB::table('ppu_ds_login_otps')->first()));
    }

    public function test_the_configured_length_is_used(): void
    {
        $this->fakeAuthenticationSettings(['otp_length' => 4]);
        $user = $this->makeUser('supervisor@example.com', ['Company Supervisor']);

        $this->service()->request('supervisor@example.com', null, null, null, LoginClient::MOBILE);

        $this->assertMatchesRegularExpression('/^\d{4}$/', $this->sentCode($user));
    }

    public function test_a_valid_code_signs_the_user_in_once_only(): void
    {
        $user = $this->makeUser('supervisor@example.com', ['Company Supervisor']);
        $this->service()->request('supervisor@example.com', null, null, null, LoginClient::MOBILE);
        $code = $this->sentCode($user);

        $signedIn = $this->service()->verify('supervisor@example.com', $code, null, 'sanctum', LoginClient::MOBILE);

        $this->assertTrue($signedIn->is($user));
        $this->assertNotNull(LoginOtp::sole()->consumed_at);
        $this->assertNotNull($user->fresh()->email_verified_at, 'An emailed code proves the address.');

        $this->expectException(ValidationException::class);
        $this->service()->verify('supervisor@example.com', $code, null, 'sanctum', LoginClient::MOBILE);
    }

    public function test_an_expired_code_is_rejected(): void
    {
        $user = $this->makeUser('supervisor@example.com', ['Company Supervisor']);
        $this->service()->request('supervisor@example.com', null, null, null, LoginClient::MOBILE);
        $code = $this->sentCode($user);

        $this->travel(11)->minutes();

        $this->expectException(ValidationException::class);
        $this->service()->verify('supervisor@example.com', $code, null, 'sanctum', LoginClient::MOBILE);
    }

    public function test_a_new_code_invalidates_the_previous_one(): void
    {
        $user = $this->makeUser('supervisor@example.com', ['Company Supervisor']);
        $this->service()->request('supervisor@example.com', null, null, null, LoginClient::MOBILE);
        $first = $this->sentCode($user);

        $this->travel(61)->seconds();
        Notification::fake();
        $this->service()->request('supervisor@example.com', null, null, null, LoginClient::MOBILE);
        $second = $this->sentCode($user);

        $this->assertSame(1, LoginOtp::query()->whereNotNull('invalidated_at')->count());

        if ($first !== $second) {
            try {
                $this->service()->verify('supervisor@example.com', $first, null, 'sanctum', LoginClient::MOBILE);
                $this->fail('The superseded code must not work.');
            } catch (ValidationException) {
                // expected
            }
        }

        $this->assertTrue($this->service()->verify('supervisor@example.com', $second, null, 'sanctum', LoginClient::MOBILE)->is($user));
    }

    public function test_the_code_is_burned_after_the_allowed_wrong_attempts(): void
    {
        $this->fakeAuthenticationSettings(['otp_max_attempts' => 3]);
        $user = $this->makeUser('supervisor@example.com', ['Company Supervisor']);
        $this->service()->request('supervisor@example.com', null, null, null, LoginClient::MOBILE);
        $code = $this->sentCode($user);
        $wrong = $code === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 3; $i++) {
            try {
                $this->service()->verify('supervisor@example.com', $wrong, null, 'sanctum', LoginClient::MOBILE);
            } catch (ValidationException) {
            }
        }

        $this->assertNotNull(LoginOtp::sole()->invalidated_at);

        $this->expectException(ValidationException::class);
        $this->service()->verify('supervisor@example.com', $code, null, 'sanctum', LoginClient::MOBILE);
    }

    public function test_repeated_failures_lock_the_email_out(): void
    {
        Event::fake([Lockout::class, Failed::class]);
        $this->fakeAuthenticationSettings(['otp_max_attempts' => 2]);
        $this->makeUser('supervisor@example.com', ['Company Supervisor']);

        for ($i = 0; $i < 4; $i++) {
            try {
                $this->service()->verify('supervisor@example.com', '123456', null, 'sanctum', LoginClient::MOBILE);
            } catch (ValidationException) {
            }
        }

        Event::assertDispatchedTimes(Failed::class, 4);

        try {
            $this->service()->verify('supervisor@example.com', '123456', null, 'sanctum', LoginClient::MOBILE);
            $this->fail('Expected a lockout.');
        } catch (OtpLoginException $e) {
            $this->assertSame(429, $e->getCode());
        }

        Event::assertDispatched(Lockout::class);
    }

    public function test_a_concurrently_consumed_code_cannot_be_used_again(): void
    {
        $user = $this->makeUser('supervisor@example.com', ['Company Supervisor']);
        $this->service()->request('supervisor@example.com', null, null, null, LoginClient::MOBILE);
        $code = $this->sentCode($user);

        // Another request consumed it between our read and our update.
        LoginOtp::query()->update(['consumed_at' => now()]);

        $this->expectException(ValidationException::class);
        $this->service()->verify('supervisor@example.com', $code, null, 'sanctum', LoginClient::MOBILE);
    }

    public function test_unknown_emails_get_the_same_answer_but_no_code(): void
    {
        $this->makeUser('student@example.com', ['Student']);

        $unknown = $this->service()->request('nobody@example.com', null, null, null, LoginClient::MOBILE);
        $known = $this->service()->request('student@example.com', null, null, null, LoginClient::MOBILE);

        $this->assertEquals($unknown->toArray(), $known->toArray());
        $this->assertSame(1, LoginOtp::count(), 'Only the real account gets a code.');
    }

    public function test_every_role_signs_in_with_the_code_since_it_replaces_the_password(): void
    {
        foreach (['Super Admin', 'Student', 'Head of Department'] as $i => $role) {
            $user = $this->makeUser("user{$i}@example.com", [$role]);

            $this->service()->request("user{$i}@example.com", null, null, null, LoginClient::MOBILE);

            $this->assertTrue($this->service()->verify("user{$i}@example.com", $this->sentCode($user), null, 'sanctum', LoginClient::MOBILE)->is($user), $role);
        }
    }

    public function test_soft_deleted_accounts_cannot_sign_in(): void
    {
        $user = $this->makeUser('supervisor@example.com', ['Company Supervisor']);
        $user->delete();

        $this->service()->request('supervisor@example.com', null, null, null, LoginClient::MOBILE);

        Notification::assertNothingSent();
    }

    public function test_resending_is_held_back_by_the_cooldown(): void
    {
        $this->makeUser('supervisor@example.com', ['Company Supervisor']);
        $this->service()->request('supervisor@example.com', null, null, null, LoginClient::MOBILE);

        try {
            $this->service()->request('supervisor@example.com', null, null, null, LoginClient::MOBILE);
            $this->fail('Expected the cooldown to apply.');
        } catch (OtpLoginException $e) {
            $this->assertSame(429, $e->getCode());
            $this->assertGreaterThan(0, $e->retryAfter);
        }

        $this->travel(61)->seconds();
        $this->service()->request('supervisor@example.com', null, null, null, LoginClient::MOBILE);
        $this->assertSame(2, LoginOtp::count());
    }

    public function test_the_hourly_limit_applies_even_to_unknown_emails(): void
    {
        $this->fakeAuthenticationSettings(['otp_max_sends_per_hour' => 2]);

        $this->service()->request('nobody@example.com', null, null, null, LoginClient::MOBILE);
        $this->travel(61)->seconds();
        $this->service()->request('nobody@example.com', null, null, null, LoginClient::MOBILE);
        $this->travel(61)->seconds();

        $this->expectException(OtpLoginException::class);
        $this->service()->request('nobody@example.com', null, null, null, LoginClient::MOBILE);
    }

    public function test_nothing_works_while_the_university_login_is_in_use(): void
    {
        $this->fakeAuthenticationSettings([], LoginMethod::PPU);
        $this->makeUser('supervisor@example.com', ['Company Supervisor']);

        $this->assertFalse($this->service()->codeLoginAvailable(LoginClient::MOBILE));

        $this->expectException(OtpLoginException::class);
        $this->service()->request('supervisor@example.com', null, null, null, LoginClient::MOBILE);
    }

    public function test_nothing_works_while_the_feature_is_switched_off(): void
    {
        $this->fakeAuthenticationSettings(['mobile_login_mode' => InternalLoginMode::PASSWORD]);

        $this->expectException(OtpLoginException::class);
        $this->service()->request('supervisor@example.com', null, null, null, LoginClient::MOBILE);
    }

    public function test_sms_is_used_when_enabled_and_the_user_has_a_phone(): void
    {
        $this->fakeAuthenticationSettings([
            'otp_sms_enabled' => true,
            'otp_default_channel' => OtpChannel::SMS,
        ]);
        $user = $this->makeUser('supervisor@example.com', ['Company Supervisor'], ['phone' => '0599123456']);

        $this->service()->request('supervisor@example.com', null, null, null, LoginClient::MOBILE);

        Notification::assertSentTo($user, LoginOtpNotification::class, fn ($n, array $channels) => $channels === [\Modules\PPUDS\Support\Sms\SmsChannel::class]);
        $this->assertSame(OtpChannel::SMS, LoginOtp::sole()->channel);
    }

    public function test_sms_falls_back_to_email_when_there_is_no_phone(): void
    {
        $this->fakeAuthenticationSettings([
            'otp_sms_enabled' => true,
            'otp_default_channel' => OtpChannel::SMS,
        ]);
        $user = $this->makeUser('supervisor@example.com', ['Company Supervisor']);

        $this->service()->request('supervisor@example.com', null, null, null, LoginClient::MOBILE);

        Notification::assertSentTo($user, LoginOtpNotification::class, fn ($n, array $channels) => $channels === ['mail']);
    }

    public function test_the_daily_sms_limit_switches_to_email(): void
    {
        $this->fakeAuthenticationSettings([
            'otp_sms_enabled' => true,
            'otp_default_channel' => OtpChannel::SMS,
            'sms_daily_limit' => 1,
        ]);
        $first = $this->makeUser('one@example.com', ['Company Supervisor'], ['phone' => '0599000001']);
        $second = $this->makeUser('two@example.com', ['Company Supervisor'], ['phone' => '0599000002']);

        $this->service()->request('one@example.com', null, null, null, LoginClient::MOBILE);
        $this->service()->request('two@example.com', null, null, null, LoginClient::MOBILE);

        Notification::assertSentTo($first, LoginOtpNotification::class, fn ($n, array $channels) => $channels !== ['mail']);
        Notification::assertSentTo($second, LoginOtpNotification::class, fn ($n, array $channels) => $channels === ['mail']);
    }

    public function test_the_user_may_pick_the_channel_only_when_allowed(): void
    {
        $this->fakeAuthenticationSettings(['otp_sms_enabled' => true]);
        $this->assertSame(OtpChannel::MAIL, $this->service()->resolveChannel(OtpChannel::SMS));

        $this->fakeAuthenticationSettings(['otp_sms_enabled' => true, 'otp_allow_channel_choice' => true]);
        $this->assertSame(OtpChannel::SMS, $this->service()->resolveChannel(OtpChannel::SMS));
    }

    public function test_a_plain_code_does_not_work_for_a_company_sign_in(): void
    {
        $company = $this->makeCompany();
        $user = $this->makeUser('supervisor@example.com', ['Company Supervisor']);
        $this->seatCompanySupervisor($company, $user);

        $this->service()->request('supervisor@example.com', null, null, null, LoginClient::MOBILE);
        $code = $this->sentCode($user);

        $this->expectException(ValidationException::class);
        $this->service()->verify('supervisor@example.com', $code, $company, 'sanctum', LoginClient::MOBILE);
    }

    // ---- Sign-in modes ----

    public function test_web_and_mobile_follow_their_own_mode(): void
    {
        $this->fakeAuthenticationSettings([
            'web_login_mode' => InternalLoginMode::PASSWORD,
            'mobile_login_mode' => InternalLoginMode::PASSWORD_THEN_CODE,
        ]);

        $this->assertTrue($this->service()->passwordLoginAllowed(LoginClient::WEB));
        $this->assertFalse($this->service()->codeLoginAvailable(LoginClient::WEB));
        $this->assertFalse($this->service()->twoStepPossible(LoginClient::WEB));

        $this->assertTrue($this->service()->passwordLoginAllowed(LoginClient::MOBILE));
        $this->assertFalse($this->service()->codeLoginAvailable(LoginClient::MOBILE));
        $this->assertTrue($this->service()->twoStepPossible(LoginClient::MOBILE));
    }

    public function test_each_mode_allows_exactly_what_it_says(): void
    {
        $expected = [
            [InternalLoginMode::PASSWORD, true, false, false],
            [InternalLoginMode::CODE, false, true, false],
            [InternalLoginMode::PASSWORD_OR_CODE, true, true, false],
            [InternalLoginMode::PASSWORD_THEN_CODE, true, false, true],
        ];

        foreach ($expected as [$mode, $password, $code, $twoStep]) {
            $this->fakeAuthenticationSettings(['mobile_login_mode' => $mode]);
            $options = $this->service()->availability(LoginClient::MOBILE);

            $this->assertSame($mode->key(), $options['mode']);
            $this->assertSame($password, $options['password_login'], $mode->key());
            $this->assertSame($code, $options['code_login'], $mode->key());
            $this->assertSame($twoStep, $options['two_step'], $mode->key());
        }
    }

    public function test_a_mode_needing_codes_falls_back_to_the_password_when_nothing_can_deliver(): void
    {
        $this->fakeAuthenticationSettings(['mobile_login_mode' => InternalLoginMode::CODE, 'otp_email_enabled' => false]);

        $this->assertSame(InternalLoginMode::PASSWORD, $this->service()->mode(LoginClient::MOBILE));
    }

    public function test_the_second_step_works_only_after_the_password_and_only_with_its_own_code(): void
    {
        $this->fakeAuthenticationSettings(['mobile_login_mode' => InternalLoginMode::PASSWORD_THEN_CODE]);
        $user = $this->makeUser('supervisor@example.com', ['Company Supervisor']);

        // Code-only sign-in is not offered in two-step mode.
        try {
            $this->service()->request('supervisor@example.com', null, null, null, LoginClient::MOBILE);
            $this->fail('Code-only sign-in must be off in two-step mode.');
        } catch (OtpLoginException $e) {
            $this->assertSame(403, $e->getCode());
        }

        $this->service()->requestAfterPassword($user, null, null, LoginClient::MOBILE);
        $code = $this->sentCode($user);

        $this->assertSame(\Modules\PPUDS\Enums\OtpPurpose::AFTER_PASSWORD, LoginOtp::sole()->purpose);

        // The same code cannot be used as a code-only sign-in.
        $this->fakeAuthenticationSettings(['mobile_login_mode' => InternalLoginMode::PASSWORD_OR_CODE]);
        try {
            $this->service()->verify('supervisor@example.com', $code, null, 'sanctum', LoginClient::MOBILE);
            $this->fail('A second-step code must not work as a code-only sign-in.');
        } catch (ValidationException) {
        }

        $this->fakeAuthenticationSettings(['mobile_login_mode' => InternalLoginMode::PASSWORD_THEN_CODE]);
        $this->assertTrue($this->service()->verifyAfterPassword($user, $code, 'sanctum', LoginClient::MOBILE)->is($user));
    }

    public function test_a_code_only_code_cannot_finish_a_two_step_sign_in(): void
    {
        $this->fakeAuthenticationSettings(['mobile_login_mode' => InternalLoginMode::CODE]);
        $user = $this->makeUser('supervisor@example.com', ['Company Supervisor']);
        $this->service()->request('supervisor@example.com', null, null, null, LoginClient::MOBILE);
        $code = $this->sentCode($user);

        $this->fakeAuthenticationSettings(['mobile_login_mode' => InternalLoginMode::PASSWORD_THEN_CODE]);

        $this->expectException(ValidationException::class);
        $this->service()->verifyAfterPassword($user, $code, 'sanctum', LoginClient::MOBILE);
    }

    public function test_the_login_ticket_names_its_user_and_expires_with_the_code(): void
    {
        $user = $this->makeUser('supervisor@example.com', ['Company Supervisor']);
        $ticket = $this->service()->issueLoginTicket($user);

        $this->assertTrue($this->service()->userFromLoginTicket($ticket)->is($user));
        $this->assertNull($this->service()->userFromLoginTicket('forged-ticket'));
        $this->assertNull($this->service()->userFromLoginTicket(null));

        $this->travel(11)->minutes();

        $this->assertNull($this->service()->userFromLoginTicket($ticket));
    }
}
