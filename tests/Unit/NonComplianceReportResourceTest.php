<?php

namespace Tests\Unit;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Modules\Branch\Entities\Branch;
use Modules\Branch\Entities\BranchWorkingHour;
use Modules\Branch\Enums\WeekDay;
use Modules\Core\Entities\User;
use Modules\PPUDS\Entities\StudentAttendance;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Entities\StudentProfile;
use Modules\PPUDS\Settings\GeneralSettings;
use Modules\PPUDS\Transformers\V1\NonComplianceReportResource;
use Tests\TestCase;

class NonComplianceReportResourceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-01-05'));

        $this->app->instance(GeneralSettings::class, (object) [
            'start_semester' => Carbon::parse('2026-01-01'),
            'end_semester' => Carbon::parse('2026-01-31'),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_resource_uses_today_as_default_date_filter_for_api_cards(): void
    {
        $data = (new NonComplianceReportResource($this->studentCompany()))
            ->toArray(Request::create('/api/v1/ppuds/non-compliance-reports'));

        $this->assertSame('248069', $data['student_number']);
        $this->assertSame(1, $data['non_compliance']['late_days']);
        $this->assertSame(150, $data['non_compliance']['total_late_minutes']);
        $this->assertSame('late_attendance', $data['non_compliance']['problems'][0]['type']);
        $this->assertSame('2026-01-05', $data['non_compliance']['problems'][0]['date']);
    }

    private function studentCompany(): StudentCompany
    {
        $studentProfile = new StudentProfile([
            'student_number' => '248069',
        ]);

        $student = new User([
            'name' => 'Test Student',
        ]);
        $student->setRelation('studentProfile', $studentProfile);

        $workingHour = new BranchWorkingHour([
            'day' => WeekDay::MONDAY,
            'start_time' => Carbon::parse('2026-01-05 08:00:00'),
            'end_time' => Carbon::parse('2026-01-05 16:00:00'),
            'is_closed' => false,
        ]);

        $branch = new Branch();
        $branch->setRelation('workingHours', new EloquentCollection([$workingHour]));

        $attendance = new StudentAttendance([
            'attendance_date' => Carbon::parse('2026-01-05'),
            'check_in' => Carbon::parse('2026-01-05 10:30:00'),
        ]);

        $studentCompany = new StudentCompany([
            'id' => 55,
            'student_id' => 11,
            'branch_id' => 22,
        ]);
        $studentCompany->setRelation('student', $student);
        $studentCompany->setRelation('branch', $branch);
        $studentCompany->setRelation('attendances', new EloquentCollection([$attendance]));
        $studentCompany->setRelation('leaveRequests', new EloquentCollection());

        return $studentCompany;
    }
}
