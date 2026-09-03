<?php

namespace Modules\PPUDS\Exports;

use Generator;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Enums\SemesterType;
use Modules\PPUDS\Services\NonComplianceReportService;

/**
 * One row per non-compliant placement, matching the cards shown on the
 * non-compliance report page.
 */
class NonComplianceReportExport implements FromGenerator, ShouldAutoSize, WithHeadings
{
    public function __construct(
        protected Builder $query,
        protected ?string $date = null,
        protected ?string $dateFrom = null,
        protected ?string $dateTo = null,
        protected int $outsideWorkRangeDistanceMeters = 200,
    ) {}

    public function headings(): array
    {
        return [
            __('Student Number'),
            __('Student Name'),
            __('Company'),
            __('Branch'),
            __('Required Working Days'),
            __('Attendance Days'),
            __('Total Non Compliance Days'),
            __('Non Attendance'),
            __('Excused Absence Days'),
            __('Unexcused Absence Days'),
            __('Late Attendance'),
            __('Total Late Hours'),
            __('Max Late Hours'),
            __('Last Late Date'),
            __('Outside Work Range'),
            __('Allowed Range (meters)'),
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

        $service = app(NonComplianceReportService::class);

        foreach ($query->lazy(200) as $studentCompany) {
            yield $this->rowFor($studentCompany, $service->summary(
                $studentCompany,
                $this->date,
                $this->dateFrom,
                $this->dateTo,
                $this->outsideWorkRangeDistanceMeters,
            ));
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
            (string) ($summary['total_non_compliance_days'] ?? 0),
            (string) ($summary['actual_absence_days'] ?? 0),
            (string) ($summary['excused_absence_days'] ?? 0),
            (string) ($summary['unexcused_absence_days'] ?? 0),
            (string) ($summary['late_days'] ?? 0),
            (string) ($summary['total_late_hours'] ?? 0),
            (string) ($summary['max_late_hours'] ?? 0),
            (string) ($summary['last_late_date'] ?? '---'),
            (string) ($summary['outside_work_range_days'] ?? 0),
            (string) ($summary['outside_work_range_distance_meters'] ?? $this->outsideWorkRangeDistanceMeters),
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
