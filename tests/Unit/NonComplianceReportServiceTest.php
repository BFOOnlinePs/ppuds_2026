<?php

namespace Tests\Unit;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Modules\Branch\Entities\Branch;
use Modules\Branch\Entities\BranchWorkingHour;
use Modules\Branch\Enums\WeekDay;
use Modules\PPUDS\Entities\StudentAttendance;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Services\NonComplianceReportService;
use Modules\PPUDS\Settings\GeneralSettings;
use Tests\TestCase;

class NonComplianceReportServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-02-01'));

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

    public function test_specific_day_filter_limits_non_compliance_summary_to_that_day(): void
    {
        $summary = app(NonComplianceReportService::class)
            ->summary($this->studentCompany(), '2026-01-05');

        $this->assertSame(0, $summary['total_absence_days']);
        $this->assertSame(1, $summary['late_days']);
        $this->assertSame(150, $summary['total_late_minutes']);
        $this->assertSame(1, $summary['total_non_compliance_days']);
    }

    public function test_date_range_filter_counts_absence_and_late_attendance_inside_range(): void
    {
        $summary = app(NonComplianceReportService::class)
            ->summary($this->studentCompany(), null, '2026-01-05', '2026-01-12');

        $this->assertSame(1, $summary['total_absence_days']);
        $this->assertSame([['date' => '2026-01-12', 'type' => 'unexcused', 'label' => __('Unexcused')]], $summary['absence_dates']);
        $this->assertSame(1, $summary['late_days']);
        $this->assertSame(2, $summary['total_non_compliance_days']);
    }

    private function studentCompany(): StudentCompany
    {
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

        $studentCompany = new StudentCompany();
        $studentCompany->setRelation('branch', $branch);
        $studentCompany->setRelation('attendances', new EloquentCollection([$attendance]));
        $studentCompany->setRelation('leaveRequests', new EloquentCollection());

        return $studentCompany;
    }
}
