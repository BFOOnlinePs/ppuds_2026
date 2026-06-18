<?php

namespace Modules\PPUDS\Exports;

use DateTimeInterface;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Enums\SemesterType;

class RegistrationsExport implements FromGenerator, ShouldAutoSize, WithHeadings
{
    public function __construct(protected Builder $query) {}

    public function headings(): array
    {
        return [
            __('Student Number'),
            __('Student Name'),
            __('Major'),
            __('Course'),
            __('Semester'),
            __('Year'),
            __('Supervisor'),
            __('University Score'),
            __('Company Score'),
            __('Final Grade'),
            __('Registered At'),
        ];
    }

    public function generator(): Generator
    {
        $query = clone $this->query;

        $query->with([
            'course.translations',
            'student.studentProfile.major.translations',
            'supervisor',
        ]);

        foreach ($query->lazy(500) as $registration) {
            yield $this->rowFor($registration);
        }
    }

    protected function rowFor(Registration $registration): array
    {
        $student = $registration->student;
        $studentProfile = $student?->studentProfile;

        return [
            (string) $studentProfile?->student_number,
            (string) $student?->name,
            (string) $studentProfile?->major?->name,
            (string) $registration->course?->name,
            $this->semesterLabel($registration->semester),
            (string) $registration->year,
            (string) $registration->supervisor?->name,
            (string) $registration->university_score,
            (string) $registration->company_score,
            (string) $registration->grade,
            $this->dateTimeValue($registration->created_at),
        ];
    }

    protected function semesterLabel(mixed $semester): string
    {
        if ($semester instanceof SemesterType) {
            return (string) $semester->getLabel();
        }

        if (is_numeric($semester)) {
            return (string) (SemesterType::tryFrom((int) $semester)?->getLabel() ?? $semester);
        }

        return (string) $semester;
    }

    protected function dateTimeValue(mixed $value): string
    {
        return $value instanceof DateTimeInterface ? $value->format('Y-m-d H:i') : (string) $value;
    }
}
