<?php

namespace Tests\Support;

use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Branch\Entities\Branch;
use Modules\Core\Entities\User;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Enums\GigEvaluationStatus;
use Modules\PPUDS\Enums\InternalLoginMode;
use Modules\PPUDS\Enums\LoginMethod;
use Modules\PPUDS\Enums\OtpChannel;
use Modules\PPUDS\Enums\ReportStatus;
use Modules\PPUDS\Enums\SemesterType;
use Modules\PPUDS\Enums\WorkLocationEnforcement;
use Modules\PPUDS\Settings\AuthenticationSettings;
use Modules\PPUDS\Settings\GeneralSettings;
use Modules\PPUDS\Settings\SmsSettings;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The project's migrations do not run on SQLite (duplicate index names
 * across tables), so — like the other database tests here — these tests
 * build just the tables they touch. The sign-in tables come from their real
 * migrations, so those are exercised too.
 */
trait BuildsAuthenticationTables
{
    protected function skipUnlessSqliteMemory(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            $this->markTestSkipped('This test builds temporary SQLite tables and only runs against :memory:.');
        }
    }

    protected function buildAuthenticationTables(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('name_en')->nullable();
            $table->string('email')->unique();
            $table->text('phone')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->boolean('notifications_enabled')->default(true);
            $table->decimal('point_balance', 12, 2)->default(0);
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('media', function (Blueprint $table): void {
            $table->id();
            $table->morphs('model');
            $table->uuid()->nullable();
            $table->string('collection_name');
            $table->string('name');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->string('disk');
            $table->string('conversions_disk')->nullable();
            $table->unsignedBigInteger('size');
            $table->json('manipulations');
            $table->json('custom_properties');
            $table->json('generated_conversions');
            $table->json('responsive_images');
            $table->unsignedInteger('order_column')->nullable();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->string('module_name')->nullable();
            $table->timestamps();
        });

        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });

        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });

        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
        });

        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('device_tokens', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('token');
            $table->string('device_name')->nullable();
            $table->timestamps();
        });

        Schema::create((new Company)->getTable(), function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('status')->default(1);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create(config('ppuds.table_prefix').'company_translations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('locale');
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create((new Branch)->getTable(), function (Blueprint $table): void {
            $table->id();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('ppu_ds_branch_company', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('branch_id');
            $table->boolean('is_main')->default(false);
            $table->timestamps();
        });

        Schema::create('ppu_ds_branch_department', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('company_department_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });

        Schema::create((new Registration)->getTable(), function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('student_id')->nullable();
            $table->unsignedBigInteger('supervisor_id')->nullable();
            $table->unsignedTinyInteger('semester')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create((new StudentCompany)->getTable(), function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('registration_id')->nullable();
            $table->unsignedBigInteger('student_id')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('evaluation_supervisor_id')->nullable();
            $table->unsignedTinyInteger('status')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });

        $migrations = [
            ...glob(base_path('Modules/PPUDS/database/migrations/2026_11_19_*.php')),
            base_path('Modules/PPUDS/database/migrations/2026_11_21_000001_add_user_id_to_company_qr_codes_table.php'),
        ];

        foreach ($migrations as $migration) {
            (require $migration)->up();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Swaps in real settings objects with the given overrides, without a
     * settings table.
     */
    protected function fakeAuthenticationSettings(array $overrides = [], LoginMethod $loginMethod = LoginMethod::SYSTEM): void
    {
        GeneralSettings::fake([
            'login_method' => $loginMethod,
            'semester_type' => SemesterType::FIRST,
            'year' => 2026,
            'start_semester' => Carbon::parse('2026-01-01'),
            'end_semester' => Carbon::parse('2026-06-30'),
            'report_status' => ReportStatus::cases()[0],
            'giz_evaluation_status' => GigEvaluationStatus::cases()[0],
            'work_location_enforcement' => WorkLocationEnforcement::DISABLED,
            'work_location_allowed_distance_meters' => 200,
            'work_location_required_major_ids' => [],
            'work_location_enforce_on_check_out' => false,
            'evaluation_supervisor_max_grade' => 25,
            'university_supervisor_max_grade' => 35,
            'company_max_grade' => 40,
            'facebook_url' => '',
            'linkedin_url' => '',
            'x_url' => '',
            'instagram_url' => '',
        ], false);

        AuthenticationSettings::fake(array_merge([
            'web_login_mode' => InternalLoginMode::CODE,
            'mobile_login_mode' => InternalLoginMode::CODE,
            'qr_login_enabled' => true,
            'personal_qr_login_enabled' => true,
            'qr_login_roles' => ['Company Supervisor'],
            'qr_code_validity_days' => 0,
            'otp_length' => 6,
            'otp_ttl_minutes' => 10,
            'otp_max_attempts' => 5,
            'otp_resend_cooldown_seconds' => 60,
            'otp_max_sends_per_hour' => 5,
            'otp_email_enabled' => true,
            'otp_sms_enabled' => false,
            'otp_default_channel' => OtpChannel::MAIL,
            'otp_allow_channel_choice' => false,
            'otp_sms_fallback_to_email' => true,
            'sms_daily_limit' => 500,
        ], $overrides), false);

        SmsSettings::fake([
            'driver' => 'log',
            'api_url' => '',
            'api_key' => '',
            'sender_id' => '',
        ], false);
    }

    protected function makeUser(string $email, array $roles = [], array $attributes = []): User
    {
        $user = User::query()->forceCreate(array_merge([
            'name' => 'Test '.$email,
            'email' => $email,
            'password' => null,
        ], $attributes));

        foreach ($roles as $role) {
            Role::findOrCreate($role, 'web');
            $user->assignRole($role);
        }

        return $user->fresh();
    }

    protected function makeCompany(int $status = 1, string $name = 'Test Company'): Company
    {
        $company = new Company;
        $company->forceFill(['status' => $status])->saveQuietly();

        DB::table(config('ppuds.table_prefix').'company_translations')->insert([
            'company_id' => $company->getKey(),
            'locale' => app()->getLocale(),
            'name' => $name,
        ]);

        return $company->fresh();
    }

    /** Seats the user as supervisor of a new branch belonging to the company. */
    protected function seatCompanySupervisor(Company $company, User $user): void
    {
        $branch = new Branch;
        $branch->saveQuietly();

        $company->branches()->attach($branch->getKey(), ['is_main' => true]);
        DB::table('ppu_ds_branch_department')->insert([
            'branch_id' => $branch->getKey(),
            'user_id' => $user->getKey(),
        ]);
    }
}
