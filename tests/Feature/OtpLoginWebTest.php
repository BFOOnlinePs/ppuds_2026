<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Entities\User;
use Modules\PPUDS\Enums\InternalLoginMode;
use Modules\PPUDS\Enums\LoginMethod;
use Modules\PPUDS\Notifications\LoginOtpNotification;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\BuildsAuthenticationTables;
use Tests\TestCase;

class OtpLoginWebTest extends TestCase
{
    use BuildsAuthenticationTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessSqliteMemory();
        $this->buildAuthenticationTables();
        $this->fakeAuthenticationSettings();

        Schema::create('activity_log', function (Blueprint $table): void {
            $table->id();
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');
            $table->string('event')->nullable();
            $table->nullableMorphs('causer', 'causer');
            $table->json('properties')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->timestamps();
        });

        // The guest layout's logo reads the site settings row.
        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('group');
            $table->string('name');
            $table->boolean('locked')->default(false);
            $table->json('payload');
            $table->timestamps();
        });
        \Illuminate\Support\Facades\DB::table('settings')->insert([
            'group' => 'general', 'name' => 'site_name', 'payload' => json_encode('PPUDS'),
        ]);

        Notification::fake();

        // The real home page needs the whole schema; any target proves the redirect.
        if (! Route::has('home')) {
            Route::get('/__home', fn () => 'home')->name('home');
        }
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

    public function test_the_page_shows_the_email_step(): void
    {
        $this->get(route('login.otp'))
            ->assertOk()
            ->assertSee(__('Send code'));
    }

    public function test_the_page_is_unavailable_under_the_university_login(): void
    {
        $this->fakeAuthenticationSettings([], LoginMethod::PPU);

        $this->get(route('login.otp'))->assertRedirect(route('login'));
    }

    public function test_a_valid_code_starts_a_session_and_is_logged(): void
    {
        $user = $this->makeUser('supervisor@example.com', ['Company Supervisor']);

        $this->post(route('login.otp.send'), ['email' => 'supervisor@example.com'])
            ->assertRedirect(route('login.otp'));

        $this->get(route('login.otp'))->assertSee(__('Code'));

        $this->post(route('login.otp.verify'), ['code' => $this->sentCode($user)])
            ->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user);

        $login = Activity::query()->where('log_name', 'auth')->where('event', 'login')->sole();
        $this->assertSame('email_code', $login->properties['sign_in_method']);
        $this->assertSame('POST', $login->properties['method'], 'The HTTP method is still recorded.');
        $this->assertSame('web', $login->properties['guard']);
    }

    public function test_a_wrong_code_returns_to_the_form_with_an_error(): void
    {
        $this->makeUser('supervisor@example.com', ['Company Supervisor']);
        $this->post(route('login.otp.send'), ['email' => 'supervisor@example.com']);

        $this->from(route('login.otp'))
            ->post(route('login.otp.verify'), ['code' => '000000'])
            ->assertRedirect(route('login.otp'))
            ->assertSessionHasErrors('code');

        $this->assertGuest();
        $this->assertTrue(Activity::query()->where('event', 'failed_login')->exists());
    }

    public function test_the_login_page_is_the_code_page_while_the_code_replaces_the_password(): void
    {
        $this->get(route('login'))->assertRedirect(route('login.otp'));

        $this->get(route('login.otp'))
            ->assertOk()
            ->assertDontSee('name="password"', false);
    }

    public function test_a_password_is_refused_even_when_posted_directly(): void
    {
        $user = $this->makeUser('admin@example.com', ['Super Admin'], ['password' => bcrypt('secret-password')]);

        $this->post(route('login.store'), ['email' => 'admin@example.com', 'password' => 'secret-password'])
            ->assertRedirect(route('login.otp'));

        $this->assertGuest();
    }

    public function test_password_only_mode_is_the_untouched_password_login(): void
    {
        $this->fakeAuthenticationSettings(['web_login_mode' => InternalLoginMode::PASSWORD]);
        $user = $this->makeUser('admin@example.com', ['Super Admin'], ['password' => bcrypt('secret-password')]);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('name="password"', false)
            ->assertDontSee(route('login.otp'));

        $this->get(route('login.otp'))->assertRedirect(route('login'));

        $this->post(route('login.store'), ['email' => 'admin@example.com', 'password' => 'secret-password']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_password_or_code_mode_offers_both(): void
    {
        $this->fakeAuthenticationSettings(['web_login_mode' => InternalLoginMode::PASSWORD_OR_CODE]);
        $user = $this->makeUser('admin@example.com', ['Super Admin'], ['password' => bcrypt('secret-password')]);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('name="password"', false)
            ->assertSee(route('login.otp'));

        $this->get(route('login.otp'))->assertOk()->assertSee(__('Sign in with a password'));

        $this->post(route('login.store'), ['email' => 'admin@example.com', 'password' => 'secret-password']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_password_then_code_mode_needs_both_steps(): void
    {
        $this->fakeAuthenticationSettings(['web_login_mode' => InternalLoginMode::PASSWORD_THEN_CODE]);
        $user = $this->makeUser('admin@example.com', ['Super Admin'], ['password' => bcrypt('secret-password')]);

        // The email-only code page is not part of this mode.
        $this->get(route('login.otp'))->assertRedirect(route('login'));

        // A wrong password never reaches the code step.
        $this->from(route('login'))
            ->post(route('login.store'), ['email' => 'admin@example.com', 'password' => 'wrong'])
            ->assertSessionHasErrors('email');
        Notification::assertNothingSent();

        // A correct password leads to the code step, not to a session.
        $this->post(route('login.store'), ['email' => 'admin@example.com', 'password' => 'secret-password', 'remember' => 'on'])
            ->assertRedirect(route('login.otp'));
        $this->assertGuest();

        $this->get(route('login.otp'))->assertOk()->assertSee(__('Back to sign in'));

        $this->post(route('login.otp.verify'), ['code' => $this->sentCode($user)])
            ->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user);

        $login = Activity::query()->where('log_name', 'auth')->where('event', 'login')->sole();
        $this->assertSame('password_and_email_code', $login->properties['sign_in_method']);
    }

    public function test_an_email_alone_cannot_start_the_second_step(): void
    {
        $this->fakeAuthenticationSettings(['web_login_mode' => InternalLoginMode::PASSWORD_THEN_CODE]);
        $this->makeUser('admin@example.com', ['Super Admin'], ['password' => bcrypt('secret-password')]);

        $this->from(route('login'))
            ->post(route('login.otp.send'), ['email' => 'admin@example.com'])
            ->assertSessionHasErrors('email');

        Notification::assertNothingSent();
    }

    public function test_the_password_login_page_still_renders_without_the_new_settings(): void
    {
        // Simulates a deploy before the settings migration has run.
        $this->app->forgetInstance(\Modules\PPUDS\Settings\AuthenticationSettings::class);
        $this->app->bind(\Modules\PPUDS\Settings\AuthenticationSettings::class, fn () => throw new \RuntimeException('settings table missing'));

        $this->get(route('login'))->assertOk()->assertSee('name="password"', false);
    }
}
