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
    public function __construct(protected Builder $query, protected ?string $date = null) {}

    public function headings(): array
    {
        return [
            __('Student Number'),
            __('Student Name'),
            __('Company'),
            __('Branch'),
            __('Absence Date'),
            __('Absence Type'),
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
            $detail = $service->detailedSummary($studentCompany, $this->date);

            $dates = collect($detail['excused_absence_dates'])
                ->map(fn (string $date) => [$date, __('Excused')])
                ->concat(
                    collect($detail['unexcused_absence_dates'])
                        ->map(fn (string $date) => [$date, __('Unexcused')])
                )
                ->sortBy(fn (array $pair) => $pair[0])
                ->values();

            foreach ($dates as [$date, $type]) {
                yield $this->rowFor($studentCompany, $date, $type);
            }
        }
    }

    protected function rowFor(StudentCompany $studentCompany, string $date, string $type): array
    {
        $student = $studentCompany->student;

        return [
            (string) ($student?->studentProfile?->student_number ?? '---'),
            (string) ($student?->name ?? '---'),
            (string) ($studentCompany->company?->name ?? '---'),
            (string) ($studentCompany->branch?->name ?? '---'),
            $date,
            $type,
        ];
    }
}
