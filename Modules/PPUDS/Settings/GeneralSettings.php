<?php

namespace Modules\PPUDS\Settings;

use Carbon\Carbon;
use Modules\PPUDS\Enums\GigEvaluationStatus;
use Modules\PPUDS\Enums\LoginMethod;
use Modules\PPUDS\Enums\ReportStatus;
use Modules\PPUDS\Enums\SemesterType;
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

    public string $facebook_url = 'https://www.facebook.com/ppu.edu';
    public string $linkedin_url = 'https://www.linkedin.com/school/palestine-polytechnic-university/';
    public string $x_url = 'https://x.com/PPU_edu';
    public string $instagram_url = 'https://www.instagram.com/ppu.edu';

    public static function group(): string
    {
        return 'ppuds_general';
    }
}
