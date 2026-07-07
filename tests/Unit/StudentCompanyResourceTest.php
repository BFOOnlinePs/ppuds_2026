<?php

namespace Tests\Unit;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Http\Request;
use Modules\Branch\Entities\Branch;
use Modules\Core\Entities\User;
use Modules\PPUDS\Entities\CompanyDepartment;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Transformers\V1\StudentCompanyResource;
use Tests\TestCase;

class StudentCompanyResourceTest extends TestCase
{
    public function test_student_company_resource_returns_company_supervisor_from_branch_department_assignment(): void
    {
        $companySupervisor = new User([
            'name' => 'Company Supervisor',
            'email' => 'company-supervisor@example.test',
            'phone' => '0599999999',
        ]);
        $companySupervisor->setAttribute('id', 77);

        $department = new CompanyDepartment();
        $department->setAttribute('id', 30);
        $department->setRelation('pivot', new Pivot([
            'user_id' => 77,
        ]));
        $department->setRelation('supervisors', new EloquentCollection([$companySupervisor]));

        $branch = new Branch();
        $branch->setAttribute('id', 20);
        $branch->setRelation('departments', new EloquentCollection([$department]));

        $studentCompany = new StudentCompany([
            'id' => 10,
            'branch_id' => 20,
            'department_id' => 30,
        ]);
        $studentCompany->setRelation('branch', $branch);
        $studentCompany->setRelation('department', $department);

        $data = (new StudentCompanyResource($studentCompany))
            ->resolve(Request::create('/api/v1/ppuds/student-companies'));

        $this->assertSame(77, $data['company_supervisor_id']);
        $this->assertSame('Company Supervisor', $data['company_supervisor_name']);
        $this->assertSame(77, $data['company_supervisor']['id']);
        $this->assertSame('Company Supervisor', $data['company_supervisor']['name']);
    }
}
