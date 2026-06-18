<?php

namespace Modules\PPUDS\Exports;

use Generator;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Enums\SemesterType;

class TodayAbsentStudentsExport implements FromGenerator, ShouldAutoSize, WithHeadings
{
    public function __construct(
        protected Builder $query,
        protected string $date,
    ) {}

    public function headings(): array
    {
        return [
            __('Date'),
            __('Student Number'),
            __('Student Name'),
            __('Email'),
            __('Phone'),
            __('Major'),
            __('Company'),
            __('Branch'),
            __('Department'),
            __('Course'),
            __('Semester'),
            __('Year'),
            __('Supervisor'),
        ];
    }

    public function generator(): Generator
    {
        $query = clone $this->query;

        $query->with([
            'branch.translations',
            'company.translations',
            'department.translations',
            'registration.course.translations',
            'registration.supervisor',
            'student.studentProfile.major.translations',
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
            $this->date,
            (string) $studentProfile?->student_number,
            (string) $student?->name,
            (string) $student?->email,
            (string) $student?->phone,
            (string) $studentProfile?->major?->name,
            (string) $studentCompany->company?->name,
            (string) $studentCompany->branch?->name,
            (string) $studentCompany->department?->name,
            (string) $registration?->course?->name,
            $this->semesterLabel($registration?->semester),
            (string) $registration?->year,
            (string) $registration?->supervisor?->name,
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
}
