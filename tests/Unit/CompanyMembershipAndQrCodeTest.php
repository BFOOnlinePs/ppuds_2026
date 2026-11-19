<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use Modules\Core\Enums\UserRole;
use Modules\PPUDS\Entities\CompanyQrCode;
use Modules\PPUDS\Enums\SemesterType;
use Modules\PPUDS\Enums\TrainingStatus;
use Modules\PPUDS\Services\CompanyMembershipService;
use Modules\PPUDS\Services\CompanyQrCodeService;
use Tests\Support\BuildsAuthenticationTables;
use Tests\TestCase;

class CompanyMembershipAndQrCodeTest extends TestCase
{
    use BuildsAuthenticationTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessSqliteMemory();
        $this->buildAuthenticationTables();
        $this->fakeAuthenticationSettings();

        config(['activitylog.enabled' => false]);
    }

    private function membership(): CompanyMembershipService
    {
        return app(CompanyMembershipService::class);
    }

    private function placement(int $companyId, array $attributes, int $semester = SemesterType::FIRST->value, int $year = 2026): void
    {
        $registrationId = DB::table('ppu_ds_registrations')->insertGetId([
            'student_id' => $attributes['student_id'] ?? null,
            'supervisor_id' => $attributes['supervisor_id'] ?? null,
            'semester' => $semester,
            'year' => $year,
        ]);

        DB::table('ppu_ds_students_companies')->insert([
            'registration_id' => $registrationId,
            'company_id' => $companyId,
            'student_id' => $attributes['student_id'] ?? null,
            'evaluation_supervisor_id' => $attributes['evaluation_supervisor_id'] ?? null,
            'status' => $attributes['status'] ?? TrainingStatus::AVAILABLE->value,
        ]);
    }

    public function test_a_company_supervisor_belongs_through_a_branch_seat(): void
    {
        $company = $this->makeCompany();
        $other = $this->makeCompany();
        $user = $this->makeUser('sup@example.com', [UserRole::COMPANY_SUPERVISOR->value]);
        $this->seatCompanySupervisor($company, $user);

        $this->assertTrue($this->membership()->belongsTo($user, $company, UserRole::COMPANY_SUPERVISOR->value));
        $this->assertFalse($this->membership()->belongsTo($user, $other, UserRole::COMPANY_SUPERVISOR->value));
    }

    public function test_a_role_the_user_does_not_hold_never_counts(): void
    {
        $company = $this->makeCompany();
        $user = $this->makeUser('sup@example.com', []);
        $this->seatCompanySupervisor($company, $user);

        $this->assertFalse($this->membership()->belongsTo($user, $company, UserRole::COMPANY_SUPERVISOR->value));
    }

    public function test_a_student_belongs_only_while_training_there_this_semester(): void
    {
        $company = $this->makeCompany();
        $student = $this->makeUser('student@example.com', [UserRole::STUDENT->value]);
        $finished = $this->makeUser('done@example.com', [UserRole::STUDENT->value]);
        $lastYear = $this->makeUser('old@example.com', [UserRole::STUDENT->value]);

        $this->placement($company->id, ['student_id' => $student->id]);
        $this->placement($company->id, ['student_id' => $finished->id, 'status' => TrainingStatus::FINISHED->value]);
        $this->placement($company->id, ['student_id' => $lastYear->id], year: 2025);

        $this->assertTrue($this->membership()->belongsTo($student, $company, UserRole::STUDENT->value));
        $this->assertFalse($this->membership()->belongsTo($finished, $company, UserRole::STUDENT->value));
        $this->assertFalse($this->membership()->belongsTo($lastYear, $company, UserRole::STUDENT->value));
    }

    public function test_evaluation_and_practical_supervisors_belong_through_placements(): void
    {
        $company = $this->makeCompany();
        $evaluator = $this->makeUser('eval@example.com', [UserRole::EVALUATION_SUPERVISOR->value]);
        $practical = $this->makeUser('prac@example.com', [UserRole::PRACTICAL_TRAINING_SUPERVISOR->value]);
        $student = $this->makeUser('student@example.com', [UserRole::STUDENT->value]);

        $this->placement($company->id, [
            'student_id' => $student->id,
            'evaluation_supervisor_id' => $evaluator->id,
            'supervisor_id' => $practical->id,
        ]);

        $this->assertTrue($this->membership()->belongsTo($evaluator, $company, UserRole::EVALUATION_SUPERVISOR->value));
        $this->assertTrue($this->membership()->belongsTo($practical, $company, UserRole::PRACTICAL_TRAINING_SUPERVISOR->value));
        $this->assertFalse($this->membership()->belongsTo($evaluator, $this->makeCompany(), UserRole::EVALUATION_SUPERVISOR->value));
    }

    public function test_roles_that_cannot_be_tied_to_a_company_are_never_members(): void
    {
        $company = $this->makeCompany();
        $admin = $this->makeUser('admin@example.com', [UserRole::ADMIN->value]);

        $this->assertNotContains(UserRole::ADMIN->value, CompanyMembershipService::supportedRoles());
        $this->assertFalse($this->membership()->belongsTo($admin, $company, UserRole::ADMIN->value));
        $this->assertNull($this->membership()->qualifyingRole($admin, $company, [UserRole::ADMIN->value]));
    }

    // ---- QR codes ----

    public function test_generating_a_code_revokes_the_previous_one(): void
    {
        $company = $this->makeCompany();
        $service = app(CompanyQrCodeService::class);

        $first = $service->generate($company);
        $second = $service->generate($company);

        $this->assertNotNull($first->fresh()->revoked_at);
        $this->assertTrue($service->activeFor($company)->is($second));
        $this->assertNull($service->resolve($service->url($first)));
        $this->assertNotNull($service->resolve($service->url($second)));
    }

    public function test_the_token_is_random_hashed_and_encrypted_at_rest(): void
    {
        $code = app(CompanyQrCodeService::class)->generate($this->makeCompany());
        $row = DB::table('ppu_ds_company_qr_codes')->find($code->id);

        $this->assertSame(43, strlen($code->token));
        $this->assertSame(hash('sha256', $code->token), $row->token_hash);
        $this->assertStringNotContainsString($code->token, $row->token);
    }

    public function test_codes_expire_after_the_configured_validity(): void
    {
        $this->fakeAuthenticationSettings(['qr_code_validity_days' => 30]);
        $service = app(CompanyQrCodeService::class);
        $code = $service->generate($this->makeCompany());

        $this->assertNotNull($service->resolve($code->token));

        $this->travel(31)->days();

        $this->assertNull($service->resolve($code->token));
        $this->assertFalse(CompanyQrCode::find($code->id)->isActive());
    }

    public function test_the_images_are_valid_svg_and_png(): void
    {
        $service = app(CompanyQrCodeService::class);
        $code = $service->generate($this->makeCompany());

        $svg = $service->svg($code);
        $this->assertStringStartsWith('<?xml', $svg);
        $this->assertNotFalse(simplexml_load_string($svg));

        $png = $service->png($code);
        $this->assertSame("\x89PNG", substr($png, 0, 4));
    }
}
