<?php

namespace Modules\PPUDS\Settings;

use Carbon\Carbon;
use Modules\PPUDS\Enums\GigEvaluationStatus;
use Modules\PPUDS\Enums\LoginMethod;
use Modules\PPUDS\Enums\ReportStatus;
use Modules\PPUDS\Enums\SemesterType;
use Modules\PPUDS\Enums\WorkLocationEnforcement;
use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public SemesterType $semester_type;
    public int $year;
    public ReportStatus $report_status;
    public LoginMethod $login_method;
    public GigEvaluationStatus $giz_evaluation_status;
    public Carbon $start_semester;
    public Carbon $end_semester;

    /** Whether students must be at the training branch to check in or out. */
    public WorkLocationEnforcement $work_location_enforcement;

    /** How far from the branch a check-in still counts as "at work", in metres. */
    public int $work_location_allowed_distance_meters;

    /**
     * Major ids that must check in from the workplace. Only consulted when
     * `work_location_enforcement` is SELECTED_MAJORS; every other major is
     * exempt in that mode.
     *
     * @var array<int, int>
     */
    public array $work_location_required_major_ids;

    /** Whether check-out is held to the same rule as check-in. */
    public bool $work_location_enforce_on_check_out;

    public string $facebook_url = 'https://www.facebook.com/ppu.edu';
    public string $linkedin_url = 'https://www.linkedin.com/school/palestine-polytechnic-university/';
    public string $x_url = 'https://x.com/PPU_edu';
    public string $instagram_url = 'https://www.instagram.com/ppu.edu';

    public static function group(): string
    {
        return 'ppuds_general';
    }
}
