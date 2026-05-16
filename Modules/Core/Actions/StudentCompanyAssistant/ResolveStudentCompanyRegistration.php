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

        $latestRegistration = Registration::query()
            ->with('course')
            ->where('student_id', $student->id)
            ->latest('id')
            ->first();

        return [
            $latestRegistration,
            $latestRegistration
                ? 'لم أجد تسجيلًا للفصل الحالي، لذلك سأستخدم آخر سجل تسجيل متاح للطالب.'
                : null,
        ];
    }
}
