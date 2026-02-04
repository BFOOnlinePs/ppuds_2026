<?php

use Modules\PPUDS\Enums\GigEvaluationStatus;
use Modules\PPUDS\Enums\LoginMethod;
use Modules\PPUDS\Enums\ReportStatus;
use Modules\PPUDS\Enums\SemesterType;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('ppuds_general.semester_type', SemesterType::FIRST->value);
        $this->migrator->add('ppuds_general.year', 2026);
        $this->migrator->add('ppuds_general.report_status', ReportStatus::CLOSED->value);
        $this->migrator->add('ppuds_general.login_method', LoginMethod::SYSTEM->value);
        $this->migrator->add('ppuds_general.giz_evaluation_status', GigEvaluationStatus::NOT_ACTIVE->value);
    }

    public function down(): void
    {
        $this->migrator->delete('ppuds_general.semester_type');
        $this->migrator->delete('ppuds_general.year');
        $this->migrator->delete('ppuds_general.report_status');
        $this->migrator->delete('ppuds_general.login_method');
        $this->migrator->delete('ppuds_general.giz_evaluation_status');
    }
};
