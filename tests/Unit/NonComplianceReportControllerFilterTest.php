<?php

namespace Tests\Unit;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Modules\PPUDS\Http\Controllers\Api\V1\NonComplianceReportController;
use Modules\PPUDS\Services\NonComplianceReportService;
use Modules\PPUDS\Settings\GeneralSettings;
use Tests\TestCase;

class NonComplianceReportControllerFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-01-05'));

        $this->app->instance(GeneralSettings::class, (object) [
            'start_semester' => Carbon::parse('2026-01-01'),
            'end_semester' => Carbon::parse('2026-01-31'),
            'year' => 2026,
            'semester_type' => null,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_distance_filter_alone_narrows_non_compliance_types_to_outside_work_range(): void
    {
        $request = Request::create('/api/v1/ppuds/non-compliance-reports', 'GET', [
            'filter' => ['outside_work_range_distance_meters' => 50],
        ]);

        $filters = $this->resolveFilters($request);

        $this->assertSame(
            [NonComplianceReportService::ISSUE_OUTSIDE_WORK_RANGE],
            $filters['non_compliance_types'],
            'Providing only the distance filter should scope the report to outside_work_range so the distance value actually affects results.'
        );
        $this->assertSame(50, $filters['outside_work_range_distance_meters']);
    }

    public function test_explicit_non_compliance_types_are_not_overridden_by_distance_filter(): void
    {
        $request = Request::create('/api/v1/ppuds/non-compliance-reports', 'GET', [
            'filter' => [
                'outside_work_range_distance_meters' => 50,
                'non_compliance_types' => 'absence,late_attendance',
            ],
        ]);

        $filters = $this->resolveFilters($request);

        $this->assertSame(['absence', 'late_attendance'], $filters['non_compliance_types']);
    }

    public function test_no_filters_provided_keeps_default_all_types_behavior(): void
    {
        $request = Request::create('/api/v1/ppuds/non-compliance-reports', 'GET');

        $filters = $this->resolveFilters($request);

        $this->assertSame([], $filters['non_compliance_types']);
    }

    private function resolveFilters(Request $request): array
    {
        $controller = new NonComplianceReportController();

        $method = new \ReflectionMethod($controller, 'resolvedFilters');
        $method->setAccessible(true);

        return $method->invoke($controller, $request);
    }
}
