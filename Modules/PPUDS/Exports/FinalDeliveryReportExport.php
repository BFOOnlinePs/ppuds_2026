<?php

namespace Modules\PPUDS\Exports;

use Generator;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Enums\SemesterType;
use Modules\PPUDS\Enums\TrainingStatus;

class FinalDeliveryReportExport implements FromGenerator, ShouldAutoSize, WithHeadings
{
    public function __construct(protected Builder $query) {}

    public function headings(): array
    {
        return [
            __('Student Number'),
            __('Student Name'),
            __('Company'),
            __('Branch'),
            __('Department'),
            __('Delivery Status'),
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
            'department.translations',
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
            (string) ($studentCompany->company?->name ?? '---'),
            (string) ($studentCompany->branch?->name ?? '---'),
            (string) ($studentCompany->department?->name ?? '---'),
            $this->statusLabel($studentCompany->status),
            $this->semesterLabel($registration?->semester),
            (string) $registration?->year,
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

        return (string) ($status ?: '---');
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
