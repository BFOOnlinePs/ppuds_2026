<?php

namespace Modules\Core\Actions\StudentCompanyAssistant;

use Modules\Core\Entities\User;
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Settings\GeneralSettings as PPUDSSettings;

class ResolveStudentCompanyRegistration
{
    public function handle(User $student): array
    {
        $settings = app(PPUDSSettings::class);
        $semester = $settings->semester_type?->value;
        $year = $settings->year;

        $currentRegistration = Registration::query()
            ->with('course')
            ->where('student_id', $student->id)
            ->when($semester, fn ($query) => $query->where('semester', $semester))
            ->when($year, fn ($query) => $query->where('year', $year))
            ->latest('id')
            ->first();

        if ($currentRegistration) {
            return [$currentRegistration, null];
        }

        return [
            null,
            sprintf(
                'وجدت الطالب، لكن لا يوجد له تسجيل ضمن الفصل والسنة المحددين في الإعدادات: %s / %s.',
                $settings->semester_type?->getLabel() ?? $semester ?? '-',
                $year ?? '-',
            ),
        ];
    }
}
