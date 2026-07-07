<?php

namespace Modules\PPUDS\Services;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Branch\Entities\BranchWorkingHour;
use Modules\PPUDS\Entities\LeaveRequest;
use Modules\PPUDS\Entities\StudentAttendance;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Settings\GeneralSettings;

class NonComplianceReportService
{
    public function applyMinimumLateHoursFilter(
        Builder $query,
        int|float|string $hours,
        ?string $date = null,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): Builder
    {
        if (! is_numeric($hours)) {
            return $query;
        }

        $minutes = max(0, (int) round(((float) $hours) * 60));
        $operator = $minutes > 0 ? '>=' : '>';
        $period = $this->reportPeriod($date, $dateFrom, $dateTo);

        if ($period === null) {
            return $query->whereRaw('1 = 0');
        }

        [$periodStart, $periodEnd] = $period;
        $attendanceTable = (new StudentAttendance())->getTable();
        $studentCompanyTable = (new StudentCompany())->getTable();
        $workingHourTable = (new BranchWorkingHour())->getTable();
        $scheduledCheckInSql = "TIMESTAMP({$attendanceTable}.attendance_date, {$workingHourTable}.start_time)";
        $lateMinutesSql = "TIMESTAMPDIFF(MINUTE, {$scheduledCheckInSql}, {$attendanceTable}.check_in)";

        return $query->whereHas('attendances', function (Builder $attendanceQuery) use (
            $attendanceTable,
            $studentCompanyTable,
            $workingHourTable,
            $lateMinutesSql,
            $operator,
            $minutes,
            $periodStart,
            $periodEnd
        ): Builder {
            return $attendanceQuery
                ->whereNotNull("{$attendanceTable}.attendance_date")
                ->whereNotNull("{$attendanceTable}.check_in")
                ->whereDate("{$attendanceTable}.attendance_date", '>=', $periodStart->toDateString())
                ->whereDate("{$attendanceTable}.attendance_date", '<=', $periodEnd->toDateString())
                ->whereExists(function ($workingHourQuery) use (
                    $attendanceTable,
                    $studentCompanyTable,
                    $workingHourTable,
                    $lateMinutesSql,
                    $operator,
                    $minutes
                ): void {
                    $workingHourQuery
                        ->select(DB::raw(1))
                        ->from($workingHourTable)
                        ->whereColumn("{$workingHourTable}.branch_id", "{$studentCompanyTable}.branch_id")
                        ->where("{$workingHourTable}.is_closed", false)
                        ->whereNotNull("{$workingHourTable}.start_time")
                        ->whereRaw("{$workingHourTable}.day = ((DAYOFWEEK({$attendanceTable}.attendance_date) % 7) + 1)")
                        ->whereRaw("{$lateMinutesSql} {$operator} ?", [$minutes]);
                });
        });
    }

    public function applyNonComplianceFilter(
        Builder $query,
        ?string $date = null,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): Builder
    {
        return $query->whereKey($this->nonCompliantStudentCompanyIds(clone $query, $date, $dateFrom, $dateTo));
    }

    public function nonCompliantStudentCompanyIds(
        Builder $query,
        ?string $date = null,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): array
    {
        return $query
            ->with([
                'attendances',
                'branch.workingHours',
                'leaveRequests',
                'registration',
            ])
            ->get()
            ->filter(function (StudentCompany $studentCompany) use ($date, $dateFrom, $dateTo): bool {
                $summary = $this->summary($studentCompany, $date, $dateFrom, $dateTo);

                return ($summary['total_absence_days'] ?? 0) > 0
                    || ($summary['late_days'] ?? 0) > 0;
            })
            ->pluck('id')
            ->values()
            ->all();
    }

    public function summary(
        StudentCompany $studentCompany,
        ?string $date = null,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): array
    {
        $studentCompany->loadMissing([
            'attendances',
            'branch.workingHours',
            'leaveRequests',
        ]);

        $period = $this->reportPeriod($date, $dateFrom, $dateTo);
        $absenceDetails = $period === null
            ? $this->emptyAbsenceDetails()
            : $this->absenceDetails($studentCompany, ...$period);
        $lateAttendances = $period === null
            ? collect()
            : $this->lateAttendances($studentCompany, ...$period);
        $lateMinutes = $lateAttendances->sum('late_minutes');
        $maxLateMinutes = (int) $lateAttendances->max('late_minutes');
        $lastLateAttendance = $lateAttendances
            ->sortByDesc(fn (array $lateAttendance): string => $lateAttendance['date'])
            ->first();

        return array_merge($absenceDetails, [
            'absence_dates' => $absenceDetails['absence_dates'],
            'excused_absence_dates' => $absenceDetails['excused_absence_dates'],
            'unexcused_absence_dates' => $absenceDetails['unexcused_absence_dates'],
            'late_days' => $lateAttendances->count(),
            'late_attendances' => $lateAttendances->values()->all(),
            'total_late_minutes' => $lateMinutes,
            'total_late_hours' => round($lateMinutes / 60, 2),
            'max_late_minutes' => $maxLateMinutes,
            'max_late_hours' => round($maxLateMinutes / 60, 2),
            'last_late_date' => $lastLateAttendance['date'] ?? null,
            'last_late_duration' => $lastLateAttendance
                ? $this->formatMinutes($lastLateAttendance['late_minutes'])
                : null,
            'total_non_compliance_days' => (int) ($absenceDetails['total_absence_days'] ?? 0) + $lateAttendances->count(),
        ]);
    }

    private function lateAttendances(StudentCompany $studentCompany, Carbon $periodStart, Carbon $periodEnd): Collection
    {
        $workingHoursByDay = $studentCompany->branch?->workingHours
            ?->filter(fn ($workingHour) => ! $workingHour->is_closed && $workingHour->day && $workingHour->start_time)
            ->keyBy(fn ($workingHour) => $workingHour->day->value);

        if (! $workingHoursByDay || $workingHoursByDay->isEmpty()) {
            return collect();
        }

        return $studentCompany->attendances
            ->toBase()
            ->filter(fn (StudentAttendance $attendance): bool => $attendance->attendance_date && $attendance->check_in)
            ->filter(fn (StudentAttendance $attendance): bool => $attendance->attendance_date->betweenIncluded($periodStart, $periodEnd))
            ->map(function (StudentAttendance $attendance) use ($workingHoursByDay): ?array {
                $workingHour = $workingHoursByDay->get($this->weekDayValue($attendance->attendance_date));

                if (! $workingHour) {
                    return null;
                }

                $scheduledCheckIn = $this->scheduledCheckIn($attendance, $workingHour->start_time);
                $lateMinutes = $this->lateMinutes($attendance, $scheduledCheckIn);

                if ($lateMinutes <= 0) {
                    return null;
                }

                return [
                    'date' => $attendance->attendance_date->toDateString(),
                    'expected_check_in' => $scheduledCheckIn->format('H:i'),
                    'check_in' => $attendance->check_in->format('H:i'),
                    'late_minutes' => $lateMinutes,
                    'late_duration' => $this->formatMinutes($lateMinutes),
                ];
            })
            ->filter()
            ->values();
    }

    private function absenceDetails(StudentCompany $studentCompany, Carbon $periodStart, Carbon $periodEnd): array
    {
        $workingDates = $this->workingDates($studentCompany, $periodStart, $periodEnd);
        $attendanceDates = $this->attendanceDates($studentCompany)->intersect($workingDates)->values();
        $leaveRequestDates = $this->leaveRequestDates($studentCompany, $periodStart, $periodEnd)
            ->intersect($workingDates)
            ->values();
        $approvedLeaveDates = $this->leaveRequestDates($studentCompany, $periodStart, $periodEnd, approvedOnly: true)
            ->intersect($workingDates)
            ->values();

        $actualAbsenceDates = $workingDates->diff($attendanceDates)->values();
        $excusedAbsenceDates = $actualAbsenceDates->intersect($approvedLeaveDates)->values();
        $unexcusedAbsenceDates = $actualAbsenceDates->diff($approvedLeaveDates)->values();

        return [
            'training_start' => $periodStart->toDateString(),
            'training_end' => $periodEnd->toDateString(),
            'required_working_days' => $workingDates->count(),
            'scheduled_training_days' => $workingDates->count(),
            'attendance_days' => $attendanceDates->count(),
            'total_absence_days' => $actualAbsenceDates->count(),
            'excused_absence_days' => $excusedAbsenceDates->count(),
            'unexcused_absence_days' => $unexcusedAbsenceDates->count(),
            'actual_absence_days' => $actualAbsenceDates->count(),
            'leave_request_days' => $leaveRequestDates->count(),
            'absence_dates' => $actualAbsenceDates
                ->map(fn (string $date): array => [
                    'date' => $date,
                    'type' => $approvedLeaveDates->contains($date) ? 'excused' : 'unexcused',
                    'label' => $approvedLeaveDates->contains($date) ? __('Excused') : __('Unexcused'),
                ])
                ->values()
                ->all(),
            'excused_absence_dates' => $excusedAbsenceDates->values()->all(),
            'unexcused_absence_dates' => $unexcusedAbsenceDates->values()->all(),
        ];
    }

    private function workingDates(StudentCompany $studentCompany, Carbon $periodStart, Carbon $periodEnd): Collection
    {
        $branch = $studentCompany->branch;

        if (! $branch?->relationLoaded('workingHours')) {
            return collect();
        }

        $openDayValues = $branch->workingHours
            ->filter(fn ($workingHour) => ! $workingHour->is_closed && $workingHour->day)
            ->map(fn ($workingHour) => $workingHour->day->value)
            ->unique()
            ->values();

        if ($openDayValues->isEmpty()) {
            return collect();
        }

        return collect(CarbonPeriod::create($periodStart, $periodEnd))
            ->filter(fn (Carbon $date) => $openDayValues->contains($this->weekDayValue($date)))
            ->map(fn (Carbon $date) => $date->toDateString())
            ->unique()
            ->sort()
            ->values();
    }

    private function attendanceDates(StudentCompany $studentCompany): Collection
    {
        return $studentCompany->attendances
            ->toBase()
            ->filter(fn (StudentAttendance $attendance) => $attendance->attendance_date && $attendance->check_in)
            ->map(fn (StudentAttendance $attendance) => $attendance->attendance_date->toDateString())
            ->unique()
            ->sort()
            ->values();
    }

    private function leaveRequestDates(
        StudentCompany $studentCompany,
        Carbon $periodStart,
        Carbon $periodEnd,
        bool $approvedOnly = false
    ): Collection {
        return $studentCompany->leaveRequests
            ->toBase()
            ->filter(fn (LeaveRequest $leaveRequest) => ! $approvedOnly || $leaveRequest->isFullyApproved())
            ->flatMap(fn (LeaveRequest $leaveRequest) => $this->leaveDatesWithinPeriod($leaveRequest, $periodStart, $periodEnd))
            ->unique()
            ->sort()
            ->values();
    }

    private function leaveDatesWithinPeriod(LeaveRequest $leaveRequest, Carbon $periodStart, Carbon $periodEnd): Collection
    {
        if (! $leaveRequest->start_at || ! $leaveRequest->end_at) {
            return collect();
        }

        $start = $leaveRequest->start_at->copy()->startOfDay();
        $end = $leaveRequest->end_at->copy()->startOfDay();

        if ($end->lt($periodStart) || $start->gt($periodEnd)) {
            return collect();
        }

        if ($start->lt($periodStart)) {
            $start = $periodStart->copy();
        }

        if ($end->gt($periodEnd)) {
            $end = $periodEnd->copy();
        }

        return collect(CarbonPeriod::create($start, $end))
            ->map(fn (Carbon $date) => $date->toDateString());
    }

    private function reportPeriod(?string $date = null, ?string $dateFrom = null, ?string $dateTo = null): ?array
    {
        $period = $this->trainingPeriod();

        if ($period === null) {
            return null;
        }

        [$periodStart, $periodEnd] = $period;
        $start = $periodStart->copy();
        $end = $periodEnd->copy();

        if (filled($date)) {
            $specificDate = $this->dateFilterValue($date);

            if ($specificDate === null) {
                return null;
            }

            $start = $specificDate;
            $end = $specificDate->copy();
        } else {
            $from = $this->dateFilterValue($dateFrom);
            $to = $this->dateFilterValue($dateTo);

            if ($from !== null) {
                $start = $from;
            }

            if ($to !== null) {
                $end = $to;
            }
        }

        if ($start->lt($periodStart)) {
            $start = $periodStart->copy();
        }

        if ($end->gt($periodEnd)) {
            $end = $periodEnd->copy();
        }

        if ($end->lt($start)) {
            return null;
        }

        return [$start->startOfDay(), $end->startOfDay()];
    }

    private function dateFilterValue(?string $date): ?Carbon
    {
        if (blank($date)) {
            return null;
        }

        try {
            return Carbon::parse($date)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function trainingPeriod(): ?array
    {
        $settings = app(GeneralSettings::class);

        $start = $settings->start_semester?->copy()->startOfDay();
        $end = $settings->end_semester?->copy()->startOfDay();

        if (! $start || ! $end || now()->lt($start)) {
            return null;
        }

        $today = now()->startOfDay();

        if ($today->lt($end)) {
            $end = $today;
        }

        if ($end->lt($start)) {
            return null;
        }

        return [$start, $end];
    }

    private function scheduledCheckIn(StudentAttendance $attendance, Carbon $startTime): Carbon
    {
        return $attendance->attendance_date
            ->copy()
            ->setTimeFromTimeString($startTime->format('H:i:s'));
    }

    private function lateMinutes(StudentAttendance $attendance, Carbon $scheduledCheckIn): int
    {
        return max(0, (int) floor(($attendance->check_in->getTimestamp() - $scheduledCheckIn->getTimestamp()) / 60));
    }

    private function weekDayValue(Carbon $date): int
    {
        return (($date->dayOfWeek + 1) % 7) + 1;
    }

    private function formatMinutes(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0';
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        if ($hours === 0) {
            return $remainingMinutes.' '.__('Minutes');
        }

        if ($remainingMinutes === 0) {
            return $hours.' '.__('Hours');
        }

        return $hours.' '.__('Hours').' '.$remainingMinutes.' '.__('Minutes');
    }

    private function emptyAbsenceDetails(): array
    {
        return [
            'training_start' => null,
            'training_end' => null,
            'required_working_days' => 0,
            'scheduled_training_days' => 0,
            'attendance_days' => 0,
            'total_absence_days' => 0,
            'excused_absence_days' => 0,
            'unexcused_absence_days' => 0,
            'actual_absence_days' => 0,
            'leave_request_days' => 0,
            'absence_dates' => [],
            'excused_absence_dates' => [],
            'unexcused_absence_dates' => [],
        ];
    }
}
