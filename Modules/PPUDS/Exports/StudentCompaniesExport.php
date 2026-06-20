<?php

namespace Modules\PPUDS\Exports;

use DateTimeInterface;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Enums\SemesterType;
use Modules\PPUDS\Enums\TrainingStatus;

class StudentCompaniesExport implements FromGenerator, ShouldAutoSize, WithHeadings
{
    public function __construct(protected Builder $query) {}

    public function headings(): array
    {
        return [
            __('Student Number'),
            __('Student Name'),
            __('Email'),
            __('Phone'),
            __('Major'),
            __('Company'),
            __('Branch'),
            __('Department'),
            __('Status'),
            __('Course'),
            __('Semester'),
            __('Year'),
            __('Supervisor'),
            __('Created At'),
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
            'registration.student.studentProfile.major.translations',
            'registration.supervisor',
            'student.studentProfile.major.translations',
        ]);

        foreach ($query->lazy(500) as $studentCompany) {
            yield $this->rowFor($studentCompany);
        }
    }

    protected function rowFor(StudentCompany $studentCompany): array
    {
        $registration = $studentCompany->registration;
        $student = $studentCompany->student ?? $registration?->student;
        $studentProfile = $student?->studentProfile;

        return [
            (string) $studentProfile?->student_number,
            (string) $student?->name,
            (string) $student?->email,
            (string) $student?->phone,
            (string) $studentProfile?->major?->name,
            (string) $studentCompany->company?->name,
            (string) $studentCompany->branch?->name,
            (string) $studentCompany->department?->name,
            $this->statusLabel($studentCompany->status),
            (string) $registration?->course?->name,
            $this->semesterLabel($registration?->semester),
            (string) $registration?->year,
            (string) $registration?->supervisor?->name,
            $this->dateTimeValue($studentCompany->created_at),
        ];
    }

    protected function statusLabel(mixed $status): string
    {
        if ($status instanceof TrainingStatus) {
            return (string) $status->getLabel();
        }

        if (is_numeric($status)) {
            return (string) (TrainingStatus::tryFrom((int) $status)?->getLabel() ?? $status);
        }

        return (string) $status;
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
