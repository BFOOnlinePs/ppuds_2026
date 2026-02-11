<?php

namespace Modules\PPUDS\Settings;

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

    public static function group(): string
    {
        return 'ppuds_general';
    }
}
