<?php

namespace Modules\PPUDS\Exports;

use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Services\AbsenceReportService;

class AbsenceDatesExport implements FromGenerator, ShouldAutoSize, WithHeadings
{
    private ?Collection $rows = null;

    public function __construct(protected Builder $query, protected ?string $dateFrom = null, protected ?string $dateTo = null) {}

    public function headings(): array
    {
        return [
            __('Student Number'),
            __('Student Name'),
            __('Company'),
            __('Branch'),
            __('Total Absence Days'),
            ...$this->dateColumnHeadings(__('Excused Absence Date'), $this->maxExcusedDates()),
            ...$this->dateColumnHeadings(__('Unexcused Absence Date'), $this->maxUnexcusedDates()),
        ];
    }

    public function generator(): Generator
    {
        foreach ($this->rows() as $row) {
            yield $this->rowFor($row);
        }
    }

    protected function rowFor(array $row): array
    {
        return [
            $row['student_number'],
            $row['student_name'],
            $row['company'],
            $row['branch'],
            (string) $row['total_absence_days'],
            ...$this->padDates($row['excused_dates'], $this->maxExcusedDates()),
            ...$this->padDates($row['unexcused_dates'], $this->maxUnexcusedDates()),
        ];
    }

    protected function dateColumnHeadings(string $label, int $count): array
    {
        return collect(range(1, max($count, 1)))
            ->map(fn (int $index): string => "{$label} {$index}")
            ->all();
    }

    protected function padDates(array $dates, int $count): array
    {
        return array_pad($dates, max($count, 1), '');
    }

    protected function maxExcusedDates(): int
    {
        return (int) $this->rows()->max(fn (array $row): int => count($row['excused_dates']));
    }

    protected function maxUnexcusedDates(): int
    {
        return (int) $this->rows()->max(fn (array $row): int => count($row['unexcused_dates']));
    }

    private function rows(): Collection
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        $query = clone $this->query;

        $query->with([
            'attendances',
            'branch.translations',
            'workingHours',
            'branch.workingHours',
            'company.translations',
            'leaveRequests',
            'student.studentProfile',
        ]);

        $service = app(AbsenceReportService::class);

        return $this->rows = $query->get()->map(function (StudentCompany $studentCompany) use ($service): array {
            $detail = $service->detailedSummary($studentCompany, $this->dateFrom, $this->dateTo);
            $student = $studentCompany->student;

            return [
                'student_number' => (string) ($student?->studentProfile?->student_number ?? '---'),
                'student_name' => (string) ($student?->name ?? '---'),
                'company' => (string) ($studentCompany->company?->name ?? '---'),
                'branch' => (string) ($studentCompany->branch?->name ?? '---'),
                'total_absence_days' => $detail['total_absence_days'] ?? 0,
                'excused_dates' => $detail['excused_absence_dates'] ?? [],
                'unexcused_dates' => $detail['unexcused_absence_dates'] ?? [],
            ];
        })->values();
    }
}
