<?php

namespace Modules\PPUDS\Exports;

use Generator;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Services\AbsenceReportService;

class AbsenceDatesExport implements FromGenerator, ShouldAutoSize, WithHeadings
{
    public function __construct(protected Builder $query, protected ?string $dateFrom = null, protected ?string $dateTo = null) {}

    public function headings(): array
    {
        return [
            __('Student Number'),
            __('Student Name'),
            __('Company'),
            __('Branch'),
            __('Total Absence Days'),
            __('Excused Absence Dates'),
            __('Unexcused Absence Dates'),
        ];
    }

    public function generator(): Generator
    {
        $query = clone $this->query;

        $query->with([
            'attendances',
            'branch.translations',
            'branch.workingHours',
            'company.translations',
            'leaveRequests',
            'student.studentProfile',
        ]);

        $service = app(AbsenceReportService::class);

        foreach ($query->lazy(500) as $studentCompany) {
            $detail = $service->detailedSummary($studentCompany, $this->dateFrom, $this->dateTo);

            yield $this->rowFor($studentCompany, $detail);
        }
    }

    protected function rowFor(StudentCompany $studentCompany, array $detail): array
    {
        $student = $studentCompany->student;

        return [
            (string) ($student?->studentProfile?->student_number ?? '---'),
            (string) ($student?->name ?? '---'),
            (string) ($studentCompany->company?->name ?? '---'),
            (string) ($studentCompany->branch?->name ?? '---'),
            (string) ($detail['total_absence_days'] ?? 0),
            $this->datesList($detail['excused_absence_dates'] ?? []),
            $this->datesList($detail['unexcused_absence_dates'] ?? []),
        ];
    }

    protected function datesList(array $dates): string
    {
        return implode(', ', $dates);
    }
}
