<?php

namespace Modules\PPUDS\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Branch\Entities\BranchWorkingHour;
use Modules\PPUDS\Entities\StudentAttendance;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Settings\GeneralSettings;

class NonComplianceReportService
{
    public function applyMinimumLateHoursFilter(Builder $query, int|float|string $hours): Builder
    {
        if (! is_numeric($hours)) {
            return $query;
        }

        $minutes = max(0, (int) round(((float) $hours) * 60));
        $operator = $minutes > 0 ? '>=' : '>';
        $period = $this->trainingPeriod();

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

    public function applyNonComplianceFilter(Builder $query): Builder
    {
        return $query->whereKey($this->nonCompliantStudentCompanyIds(clone $query));
    }

    public function nonCompliantStudentCompanyIds(Builder $query): array
    {
        return $query
            ->with([
                'attendances',
                'branch.workingHours',
                'leaveRequests',
                'registration',
            ])
            ->get()
            ->filter(function (StudentCompany $studentCompany): bool {
                $summary = $this->summary($studentCompany);

                return ($summary['total_absence_days'] ?? 0) > 0
                    || ($summary['late_days'] ?? 0) > 0;
            })
            ->pluck('id')
            ->values()
            ->all();
    }

    public function summary(StudentCompany $studentCompany): array
    {
        $studentCompany->loadMissing([
            'attendances',
            'branch.workingHours',
            'leaveRequests',
        ]);

        $absenceSummary = app(AbsenceReportService::class)->summary($studentCompany);
        $period = $this->trainingPeriod();
        $lateAttendances = $period === null
            ? collect()
            : $this->lateAttendances($studentCompany, ...$period);
        $lateMinutes = $lateAttendances->sum('late_minutes');
        $maxLateMinutes = (int) $lateAttendances->max('late_minutes');
        $lastLateAttendance = $lateAttendances
            ->sortByDesc(fn (array $lateAttendance): string => $lateAttendance['date'])
            ->first();

        return array_merge($absenceSummary, [
            'late_days' => $lateAttendances->count(),
            'total_late_minutes' => $lateMinutes,
            'total_late_hours' => round($lateMinutes / 60, 2),
            'max_late_minutes' => $maxLateMinutes,
            'max_late_hours' => round($maxLateMinutes / 60, 2),
            'last_late_date' => $lastLateAttendance['date'] ?? null,
            'last_late_duration' => $lastLateAttendance
                ? $this->formatMinutes($lastLateAttendance['late_minutes'])
                : null,
            'total_non_compliance_days' => (int) ($absenceSummary['total_absence_days'] ?? 0) + $lateAttendances->count(),
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

                $lateMinutes = $this->lateMinutes($attendance, $workingHour->start_time);

                if ($lateMinutes <= 0) {
                    return null;
                }

                return [
                    'date' => $attendance->attendance_date->toDateString(),
                    'late_minutes' => $lateMinutes,
                ];
            })
            ->filter()
            ->values();
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

    private function lateMinutes(StudentAttendance $attendance, Carbon $startTime): int
    {
        $scheduledCheckIn = $attendance->attendance_date
            ->copy()
            ->setTimeFromTimeString($startTime->format('H:i:s'));

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
}
