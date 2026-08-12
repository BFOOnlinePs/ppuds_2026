<?php

namespace Modules\PPUDS\Exports;

use Generator;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Enums\SemesterType;
use Modules\PPUDS\Enums\StudentGender;

class ReportExport implements FromGenerator, ShouldAutoSize, WithHeadings
{
    public function __construct(protected Builder $query) {}

    public function headings(): array
    {
        return [
            __('Student Number'),
            __('Student Name'),
            __('Gender'),
            __('Company'),
            __('Attendance Days'),
            __('Actual Working Hours'),
            __('Required Training Days (Until Training End)'),
            __('Attended Days (Until Today)'),
            __('Semester'),
            __('Year'),
        ];
    }

    public function generator(): Generator
    {
        $query = clone $this->query;

        $query->with([
            'branch.translations',
            'company.translations',
            'registration',
            'student.studentProfile',
        ]);

        foreach ($query->lazy(500) as $studentCompany) {
            yield $this->rowFor($studentCompany);
        }
    }

    protected function rowFor(StudentCompany $studentCompany): array
    {
        $student = $studentCompany->student;
        $studentProfile = $student?->studentProfile;
        $registration = $studentCompany->registration;

        return [
            (string) ($studentProfile?->student_number ?? '---'),
            (string) ($student?->name ?? '---'),
            $this->genderLabel($studentProfile?->gender),
            (string) ($studentCompany->company?->name ?? '---'),
            (string) ($studentCompany->attendance_days ?? 0),
            (string) ($studentCompany->actual_working_hours ?? 0),
            (string) ($studentCompany->branch?->required_training_days ?? '---'),
            (string) ($studentCompany->branch?->attended_training_days ?? '---'),
            $this->semesterLabel($registration?->semester),
            (string) $registration?->year,
        ];
    }

    protected function genderLabel(mixed $gender): string
    {
        if ($gender instanceof StudentGender) {
            return (string) $gender->getLabel();
        }

        if (is_numeric($gender)) {
            return (string) (StudentGender::tryFrom((int) $gender)?->getLabel() ?? $gender);
        }

        return (string) ($gender ?: '---');
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
}
