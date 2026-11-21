<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Core\Entities\User;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\CompanyQrCode;
use Modules\PPUDS\Enums\InternalLoginMode;
use Modules\PPUDS\Enums\LoginMethod;
use Modules\PPUDS\Services\CompanyQrCodeService;
use Tests\Support\BuildsAuthenticationTables;
use Tests\TestCase;

/**
 * A person's own QR code signs them straight in from the app — no email, no
 * code — while the company's printed code still needs the one-time code.
 */
class PersonalQrLoginTest extends TestCase
{
    use BuildsAuthenticationTables;

    private Company $company;

    private User $supervisor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessSqliteMemory();
        $this->buildAuthenticationTables();
        $this->fakeAuthenticationSettings();

        config(['activitylog.enabled' => false]);
        Notification::fake();

        $this->company = $this->makeCompany(name: 'Acme Training');
        $this->supervisor = $this->makeUser('supervisor@example.com', ['Company Supervisor']);
        $this->seatCompanySupervisor($this->company, $this->supervisor);
    }

    private function qrService(): CompanyQrCodeService
    {
        return app(CompanyQrCodeService::class);
    }

    private function personalUrl(): string
    {
        return $this->qrService()->url($this->qrService()->generatePersonal($this->company, $this->supervisor));
    }

    public function test_a_personal_code_signs_its_holder_straight_in(): void
    {
        $url = $this->personalUrl();

        $this->postJson('/api/v1/auth/qr/scan', ['qr_token' => $url, 'device_name' => 'Pixel 9', 'fcm_token' => 'fcm-1'])
            ->assertOk()
            ->assertJsonPath('data.type', 'personal')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.id', $this->supervisor->id);

        // No code is ever sent for a personal code.
        Notification::assertNothingSent();

        $this->assertSame(1, CompanyQrCode::query()->personal()->sole()->use_count);
        $this->assertDatabaseHas('device_tokens', ['user_id' => $this->supervisor->id, 'token' => 'fcm-1']);
        $this->assertSame('Pixel 9', $this->supervisor->tokens()->sole()->name);
    }

    public function test_the_company_code_still_asks_for_the_one_time_code(): void
    {
        $url = $this->qrService()->url($this->qrService()->generate($this->company));

        $this->postJson('/api/v1/auth/qr/scan', ['qr_token' => $url])
            ->assertOk()
            ->assertJsonPath('data.type', 'company')
            ->assertJsonPath('data.company.name', 'Acme Training')
            ->assertJsonMissingPath('data.token');
    }

    public function test_a_revoked_or_regenerated_personal_code_stops_working(): void
    {
        $first = $this->personalUrl();
        $second = $this->personalUrl(); // regenerating revokes the first

        $this->postJson('/api/v1/auth/qr/scan', ['qr_token' => $first])->assertNotFound();
        $this->postJson('/api/v1/auth/qr/scan', ['qr_token' => $second])->assertOk();

        $code = $this->qrService()->activePersonalFor($this->company, $this->supervisor);
        $this->qrService()->revoke($code);

        $this->postJson('/api/v1/auth/qr/scan', ['qr_token' => $second])->assertNotFound();
    }

    public function test_a_holder_who_left_the_company_can_no_longer_use_their_code(): void
    {
        $url = $this->personalUrl();

        DB::table('ppu_ds_branch_department')->where('user_id', $this->supervisor->id)->delete();

        $this->postJson('/api/v1/auth/qr/scan', ['qr_token' => $url])->assertNotFound();
    }

    public function test_a_deleted_account_can_no_longer_use_its_code(): void
    {
        $url = $this->personalUrl();

        $this->supervisor->delete();

        $this->postJson('/api/v1/auth/qr/scan', ['qr_token' => $url])->assertNotFound();
    }

    public function test_an_inactive_company_disables_its_personal_codes(): void
    {
        $url = $this->personalUrl();

        $this->company->forceFill(['status' => 0])->saveQuietly();

        $this->postJson('/api/v1/auth/qr/scan', ['qr_token' => $url])->assertNotFound();
    }

    public function test_personal_codes_are_off_by_their_own_switch(): void
    {
        $url = $this->personalUrl();

        $this->fakeAuthenticationSettings(['personal_qr_login_enabled' => false]);

        // The company code is still on, so a personal scan simply reads as unknown.
        $this->postJson('/api/v1/auth/qr/scan', ['qr_token' => $url])->assertNotFound();

        $this->fakeAuthenticationSettings(['personal_qr_login_enabled' => false, 'qr_login_enabled' => false]);
        $this->postJson('/api/v1/auth/qr/scan', ['qr_token' => $url])->assertForbidden();
    }

    public function test_personal_codes_follow_the_allowed_qr_roles(): void
    {
        $url = $this->personalUrl();

        $this->fakeAuthenticationSettings(['qr_login_roles' => ['Student']]);

        $this->postJson('/api/v1/auth/qr/scan', ['qr_token' => $url])->assertNotFound();
    }

    public function test_nothing_works_under_the_university_login(): void
    {
        $url = $this->personalUrl();

        $this->fakeAuthenticationSettings([], LoginMethod::PPU);

        $this->postJson('/api/v1/auth/qr/scan', ['qr_token' => $url])->assertForbidden();
    }

    public function test_personal_codes_work_whatever_the_mobile_sign_in_mode(): void
    {
        $url = $this->personalUrl();

        $this->fakeAuthenticationSettings(['mobile_login_mode' => InternalLoginMode::PASSWORD]);

        $this->postJson('/api/v1/auth/qr/scan', ['qr_token' => $url])->assertOk();
    }

    public function test_a_personal_code_cannot_be_used_as_a_company_code(): void
    {
        $url = $this->personalUrl();

        $this->postJson('/api/v1/auth/company-qr/resolve', ['qr_token' => $url])->assertNotFound();
        $this->postJson('/api/v1/auth/otp/request', ['email' => 'supervisor@example.com', 'qr_token' => $url])->assertNotFound();
    }

    public function test_company_and_personal_codes_never_revoke_each_other(): void
    {
        $companyCode = $this->qrService()->generate($this->company);
        $personalCode = $this->qrService()->generatePersonal($this->company, $this->supervisor);

        $this->qrService()->generate($this->company);
        $this->assertTrue($personalCode->fresh()->isActive(), 'A new company code must not revoke personal codes.');

        $this->qrService()->generatePersonal($this->company, $this->supervisor);
        $this->assertNotNull($companyCode->fresh()->revoked_at);
        $this->assertNotNull($this->qrService()->activeFor($this->company), 'A new personal code must not revoke the company code.');
    }

    public function test_personal_codes_are_only_issued_to_people_of_the_company(): void
    {
        $outsider = $this->makeUser('outsider@example.com', ['Company Supervisor']);

        $this->expectException(\InvalidArgumentException::class);
        $this->qrService()->generatePersonal($this->company, $outsider);
    }

    public function test_the_browser_page_of_a_personal_code_never_names_its_holder(): void
    {
        \Illuminate\Support\Facades\Schema::create('app_versions', function ($table): void {
            $table->id();
            $table->string('platform');
            $table->string('store_url')->nullable();
            $table->timestamps();
        });
        \Illuminate\Support\Facades\Schema::create('settings', function ($table): void {
            $table->id();
            $table->string('group');
            $table->string('name');
            $table->boolean('locked')->default(false);
            $table->json('payload');
            $table->timestamps();
        });
        DB::table('settings')->insert(['group' => 'general', 'name' => 'site_name', 'payload' => json_encode('PPUDS')]);

        $token = $this->qrService()->generatePersonal($this->company, $this->supervisor)->token;

        $this->get(route('company-qr.landing', ['token' => $token]))
            ->assertOk()
            ->assertSee(__('Personal sign-in code'))
            ->assertDontSee('supervisor@example.com')
            ->assertDontSee($this->supervisor->name);
    }

    /**
     * The setup the administration asked for: students sign in with a
     * one-time code and never with a QR code, while company supervisors scan
     * their personal QR code and get in without any code.
     */
    public function test_students_use_the_code_while_supervisors_scan_their_personal_code(): void
    {
        $this->fakeAuthenticationSettings([
            'mobile_login_mode' => InternalLoginMode::CODE,
            'qr_login_enabled' => false,
            'personal_qr_login_enabled' => true,
            'qr_login_roles' => ['Company Supervisor'],
        ]);

        // Supervisor: scan, and in — no email, no code.
        $this->postJson('/api/v1/auth/qr/scan', ['qr_token' => $this->personalUrl()])
            ->assertOk()
            ->assertJsonPath('data.type', 'personal')
            ->assertJsonPath('data.user.id', $this->supervisor->id);
        Notification::assertNothingSent();

        // Student: email, then the code sent to them.
        $student = $this->makeUser('student@example.com', ['Student']);
        $this->placeStudent($student);

        $this->postJson('/api/v1/auth/otp/request', ['email' => 'student@example.com'])->assertOk();

        $code = null;
        Notification::assertSentTo($student, \Modules\PPUDS\Notifications\LoginOtpNotification::class, function ($notification) use (&$code): bool {
            $code = (fn () => $this->code)->call($notification);

            return true;
        });

        $this->postJson('/api/v1/auth/otp/verify', ['email' => 'student@example.com', 'code' => $code])
            ->assertOk()
            ->assertJsonPath('data.user.id', $student->id);

        // A student can never be issued a personal QR code, even when training at the company…
        try {
            $this->qrService()->generatePersonal($this->company, $student);
            $this->fail('Students must not get a personal QR code.');
        } catch (\InvalidArgumentException) {
        }

        // …and no password works for anyone in this mode.
        $this->postJson('/api/v1/auth/login', ['email' => 'student@example.com', 'password' => 'anything'])->assertForbidden();
    }

    private function placeStudent(User $student): void
    {
        $registrationId = DB::table('ppu_ds_registrations')->insertGetId([
            'student_id' => $student->id,
            'semester' => \Modules\PPUDS\Enums\SemesterType::FIRST->value,
            'year' => 2026,
        ]);

        DB::table('ppu_ds_students_companies')->insert([
            'registration_id' => $registrationId,
            'company_id' => $this->company->id,
            'student_id' => $student->id,
            'status' => \Modules\PPUDS\Enums\TrainingStatus::AVAILABLE->value,
        ]);
    }

    public function test_the_options_report_personal_qr_sign_in(): void
    {
        $this->getJson('/api/v1/auth/options')->assertJsonPath('data.personal_qr_login', true);

        $this->fakeAuthenticationSettings(['personal_qr_login_enabled' => false]);

        $this->getJson('/api/v1/auth/options')->assertJsonPath('data.personal_qr_login', false);
    }
}
