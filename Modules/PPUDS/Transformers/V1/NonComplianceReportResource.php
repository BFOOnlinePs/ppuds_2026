<?php

namespace Modules\PPUDS\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\PPUDS\Services\NonComplianceReportService;

class NonComplianceReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $summary = app(NonComplianceReportService::class)
            ->summary($this->resource, ...[
                ...self::dateFilters($request),
                self::outsideWorkRangeDistanceMeters($request),
            ]);

        return [
            'id' => $this->id,
            'student_company_id' => $this->id,
            'student_id' => $this->student_id,
            'student_number' => $this->student?->studentProfile?->student_number,
            'student_name' => $this->student?->name,
            'company_id' => $this->company_id,
            'company_name' => $this->company?->name,
            'branch_id' => $this->branch_id,
            'branch_name' => $this->branch?->name,
            'supervisor_id' => $this->registration?->supervisor_id,
            'year' => $this->registration?->year,
            'semester' => $this->registration?->semester?->value,
            'semester_label' => $this->registration?->semester?->getLabel(),
            'non_compliance' => [
                'total_days' => (int) ($summary['total_non_compliance_days'] ?? 0),
                'absence_days' => (int) ($summary['total_absence_days'] ?? 0),
                'excused_absence_days' => (int) ($summary['excused_absence_days'] ?? 0),
                'unexcused_absence_days' => (int) ($summary['unexcused_absence_days'] ?? 0),
                'late_days' => (int) ($summary['late_days'] ?? 0),
                'total_late_minutes' => (int) ($summary['total_late_minutes'] ?? 0),
                'total_late_hours' => (float) ($summary['total_late_hours'] ?? 0),
                'max_late_minutes' => (int) ($summary['max_late_minutes'] ?? 0),
                'max_late_hours' => (float) ($summary['max_late_hours'] ?? 0),
                'last_late_date' => $summary['last_late_date'] ?? null,
                'last_late_duration' => $summary['last_late_duration'] ?? null,
                'absence_dates' => $summary['absence_dates'] ?? [],
                'late_attendances' => $summary['late_attendances'] ?? [],
                'outside_work_range_days' => (int) ($summary['outside_work_range_days'] ?? 0),
                'outside_work_range_distance_meters' => (int) ($summary['outside_work_range_distance_meters'] ?? 200),
                'outside_work_range_attendances' => $summary['outside_work_range_attendances'] ?? [],
                'problems' => $this->problems($summary),
            ],
        ];
    }

    public static function dateFilters(?Request $request = null): array
    {
        $request ??= request();
        $date = self::stringFilterValue($request->input('filter.date'));
        $dateFrom = self::stringFilterValue($request->input('filter.date_from'));
        $dateTo = self::stringFilterValue($request->input('filter.date_to'));

        if ($date === null && $dateFrom === null && $dateTo === null) {
            $date = now()->toDateString();
        }

        return [$date, $dateFrom, $dateTo];
    }

    private function problems(array $summary): array
    {
        $absenceProblems = collect($summary['absence_dates'] ?? [])
            ->map(fn (array $absence): array => [
                'type' => 'absence',
                'date' => $absence['date'] ?? null,
                'label' => $absence['label'] ?? null,
                'absence_type' => $absence['type'] ?? null,
            ]);

        $lateProblems = collect($summary['late_attendances'] ?? [])
            ->map(fn (array $lateAttendance): array => [
                'type' => 'late_attendance',
                'date' => $lateAttendance['date'] ?? null,
                'expected_check_in' => $lateAttendance['expected_check_in'] ?? null,
                'check_in' => $lateAttendance['check_in'] ?? null,
                'late_minutes' => (int) ($lateAttendance['late_minutes'] ?? 0),
                'late_duration' => $lateAttendance['late_duration'] ?? null,
            ]);

        $outsideWorkRangeProblems = collect($summary['outside_work_range_attendances'] ?? [])
            ->map(fn (array $attendance): array => [
                'type' => 'outside_work_range',
                'date' => $attendance['date'] ?? null,
                'check_in' => $attendance['check_in'] ?? null,
                'distance_meters' => (int) ($attendance['distance_meters'] ?? 0),
                'distance_label' => $attendance['distance_label'] ?? null,
                'attendance_latitude' => $attendance['attendance_latitude'] ?? null,
                'attendance_longitude' => $attendance['attendance_longitude'] ?? null,
                'branch_latitude' => $attendance['branch_latitude'] ?? null,
                'branch_longitude' => $attendance['branch_longitude'] ?? null,
            ]);

        return $absenceProblems
            ->merge($lateProblems)
            ->merge($outsideWorkRangeProblems)
            ->sortBy('date')
            ->values()
            ->all();
    }

    private static function outsideWorkRangeDistanceMeters(?Request $request = null): int
    {
        $request ??= request();
        $distance = (int) self::stringFilterValue($request->input('filter.outside_work_range_distance_meters'));

        return $distance > 0 ? $distance : 200;
    }

    private static function stringFilterValue(mixed $value): ?string
    {
        $value = is_array($value) ? reset($value) : $value;

        return filled($value) ? (string) $value : null;
    }
}
