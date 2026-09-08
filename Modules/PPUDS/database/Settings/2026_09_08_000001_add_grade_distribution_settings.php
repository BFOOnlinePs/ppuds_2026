<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // توزيع العلامات على الجهات الثلاث، ومجموعها هو العلامة الكلية.
        // 25 هي القيمة التي كانت مثبّتة في شاشة مشرف التقييم قبل نقلها للإعدادات.
        $this->migrator->add('ppuds_general.evaluation_supervisor_max_grade', 25);
        $this->migrator->add('ppuds_general.university_supervisor_max_grade', 35);
        $this->migrator->add('ppuds_general.company_max_grade', 40);
    }

    public function down(): void
    {
        $this->migrator->delete('ppuds_general.evaluation_supervisor_max_grade');
        $this->migrator->delete('ppuds_general.university_supervisor_max_grade');
        $this->migrator->delete('ppuds_general.company_max_grade');
    }
};
