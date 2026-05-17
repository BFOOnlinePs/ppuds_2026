<?php

namespace Modules\PPUDS\Http\Controllers\Api\V1\Concerns;

use Illuminate\Http\JsonResponse;
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Entities\StudentAttendance;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Enums\SemesterType;
use Modules\PPUDS\Settings\GeneralSettings;

trait EnsuresCurrentRegistration
{
    protected function ensureRegistrationInCurrentSemester(?Registration $registration): ?JsonResponse
    {
        if ($this->registrationIsInCurrentSemester($registration)) {
            return null;
        }

        return $this->currentRegistrationErrorResponse();
    }

    protected function ensureStudentCompanyInCurrentSemester(int|StudentCompany|null $studentCompany): ?JsonResponse
    {
        if (is_int($studentCompany)) {
            $studentCompany = StudentCompany::query()
                ->with('registration')
                ->find($studentCompany);
        } elseif ($studentCompany) {
            $studentCompany->loadMissing('registration');
        }

        if ($studentCompany && $this->registrationIsInCurrentSemester($studentCompany->registration)) {
            return null;
        }

        return $this->currentRegistrationErrorResponse();
    }

    protected function ensureStudentAttendanceInCurrentSemester(int|StudentAttendance|null $studentAttendance): ?JsonResponse
    {
        if (is_int($studentAttendance)) {
            $studentAttendance = StudentAttendance::query()
                ->with('studentCompany.registration')
                ->find($studentAttendance);
        } elseif ($studentAttendance) {
            $studentAttendance->loadMissing('studentCompany.registration');
        }

        if ($studentAttendance && $this->registrationIsInCurrentSemester($studentAttendance->studentCompany?->registration)) {
            return null;
        }

        return $this->currentRegistrationErrorResponse();
    }

    protected function ensureRelatedStudentCompanyInCurrentSemester($model): ?JsonResponse
    {
        $model->loadMissing('studentCompany.registration');

        return $this->ensureStudentCompanyInCurrentSemester($model->studentCompany);
    }

    protected function registrationIsInCurrentSemester(?Registration $registration): bool
    {
        if (! $registration) {
            return false;
        }

        $settings = app(GeneralSettings::class);

        return (int) $registration->year === (int) $settings->year
            && $this->semesterValue($registration->semester) === $this->semesterValue($settings->semester_type);
    }

    private function semesterValue(SemesterType|int|string|null $semester): ?int
    {
        if ($semester instanceof SemesterType) {
            return $semester->value;
        }

        return $semester !== null ? (int) $semester : null;
    }

    private function currentRegistrationErrorResponse(): JsonResponse
    {
        return $this->errorResponse(__('This record does not belong to the current registration semester.'), 422);
    }
}
