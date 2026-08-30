<?php

use Modules\PPUDS\Enums\WorkLocationEnforcement;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Starts off, so enabling geofenced attendance stays a deliberate act.
        $this->migrator->add('ppuds_general.work_location_enforcement', WorkLocationEnforcement::DISABLED->value);
        $this->migrator->add('ppuds_general.work_location_allowed_distance_meters', 200);
        $this->migrator->add('ppuds_general.work_location_required_major_ids', []);
        $this->migrator->add('ppuds_general.work_location_enforce_on_check_out', true);
    }

    public function down(): void
    {
        $this->migrator->delete('ppuds_general.work_location_enforcement');
        $this->migrator->delete('ppuds_general.work_location_allowed_distance_meters');
        $this->migrator->delete('ppuds_general.work_location_required_major_ids');
        $this->migrator->delete('ppuds_general.work_location_enforce_on_check_out');
    }
};
