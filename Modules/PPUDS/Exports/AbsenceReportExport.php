<?php

namespace Modules\PPUDS\Exports;

use Generator;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Enums\SemesterType;
use Modules\PPUDS\Services\AbsenceReportService;

class AbsenceReportExport implements FromGenerator, ShouldAutoSize, WithHeadings
{
    public function __construct(protected Builder $query, protected ?string $dateFrom = null, protected ?string $dateTo = null) {}

    public function headings(): array
    {
        return [
            __('Student Number'),
            __('Student Name'),
            __('Company'),
            __('Branch'),
            __('Required Working Days'),
            __('Attendance Days'),
            __('Actual Working Hours'),
            __('Total Absence Days'),
            __('Excused Absence Days'),
            __('Unexcused Absence Days'),
            __('Actual Absence Days'),
            __('Leave Request Days'),
            __('Semester'),
            __('Year'),
        ];
    }

    public function generator(): Generator
    {
        $query = clone $this->query;

        $query->with([
            'attendances',
            'branch.translations',
            'workingHours',
            'branch.workingHours',
            'company.translations',
            'leaveRequests',
            'registration',
            'student.studentProfile',
        ]);

        $service = app(AbsenceReportService::class);

        foreach ($query->lazy(500) as $studentCompany) {
            yield $this->rowFor($studentCompany, $service->summary($studentCompany, $this->dateFrom, $this->dateTo));
        }
    }

    protected function rowFor(StudentCompany $studentCompany, array $summary): array
    {
        $student = $studentCompany->student;
        $registration = $studentCompany->registration;

        return [
            (string) ($student?->studentProfile?->student_number ?? '---'),
            (string) ($student?->name ?? '---'),
            (string) ($studentCompany->company?->name ?? '---'),
            (string) ($studentCompany->branch?->name ?? '---'),
            (string) ($summary['required_working_days'] ?? 0),
            (string) ($summary['attendance_days'] ?? 0),
            (string) ($studentCompany->actual_working_hours ?? 0),
            (string) ($summary['total_absence_days'] ?? 0),
            (string) ($summary['excused_absence_days'] ?? 0),
            (string) ($summary['unexcused_absence_days'] ?? 0),
            (string) ($summary['actual_absence_days'] ?? 0),
            (string) ($summary['leave_request_days'] ?? 0),
            $this->semesterLabel($registration?->semester),
            (string) $registration?->year,
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
