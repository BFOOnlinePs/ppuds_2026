<?php

namespace Modules\PPUDS\Settings;

use Spatie\LaravelSettings\Settings;
use Modules\PPUDS\Enums\Enums\SemesterType;
use Modules\PPUDS\Enums\Enums\ReportStatus;
use Modules\PPUDS\Enums\Enums\LoginMethod;
use Modules\PPUDS\Enums\Enums\GigEvaluationStatus;

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
