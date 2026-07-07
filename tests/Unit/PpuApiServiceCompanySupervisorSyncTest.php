<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Http;
use Modules\Branch\Entities\Branch;
use Modules\Core\Entities\User;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Services\PpuApiService;
use Tests\TestCase;

class PpuApiServiceCompanySupervisorSyncTest extends TestCase
{
    public function test_add_company_treats_success_false_already_exists_response_as_already_registered(): void
    {
        config(['services.ppu_api.base_url' => 'https://ppu.example.test']);

        Http::fake([
            'https://ppu.example.test/api/DualStudies/Company/Add' => Http::response([
                'success' => false,
                'message' => 'Company already exists',
            ], 200),
        ]);

        $result = (new PpuApiService)->addCompanyToUniversity(
            $this->companyWithSupervisor(),
            token: 'token',
            refreshToken: 'refresh-token',
            sendEvenIfCompanyExists: true,
        );

        $this->assertTrue($result['success']);
        $this->assertSame('already_exists', $result['operation']);
    }

    public function test_add_company_returns_status_and_response_when_university_api_rejects_request(): void
    {
        config(['services.ppu_api.base_url' => 'https://ppu.example.test']);

        Http::fake([
            'https://ppu.example.test/api/DualStudies/Company/Add' => Http::response([
                'message' => 'Invalid supervisor mobile',
            ], 422),
        ]);

        $result = (new PpuApiService)->addCompanyToUniversity(
            $this->companyWithSupervisor(),
            token: 'token',
            refreshToken: 'refresh-token',
            sendEvenIfCompanyExists: true,
        );

        $this->assertFalse($result['success']);
        $this->assertSame('failed', $result['operation']);
        $this->assertSame(422, $result['status']);
        $this->assertSame('Invalid supervisor mobile', $result['response']['message']);
    }

    private function companyWithSupervisor(): Company
    {
        $supervisor = new User([
            'name' => 'Company Supervisor',
            'name_en' => 'Company Supervisor',
            'email' => 'supervisor@example.test',
            'phone' => '0599999999',
        ]);
        $supervisor->setAttribute('id', 10);

        $branch = new Branch([
            'email' => 'branch@example.test',
            'phone' => '022222222',
        ]);
        $branch->setRelation('supervisors', collect([$supervisor]));

        $company = new Company(['id' => 20]);
        $company->setRelation('branches', collect([$branch]));
        $company->setRelation('translations', collect());

        return $company;
    }
}
