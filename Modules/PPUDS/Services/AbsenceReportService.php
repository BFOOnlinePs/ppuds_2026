<?php

namespace Modules\PPUDS\Services;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Modules\PPUDS\Entities\LeaveRequest;
use Modules\PPUDS\Entities\StudentAttendance;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Settings\GeneralSettings;

class AbsenceReportService
{
    public function summary(StudentCompany $studentCompany): array
    {
        $period = $this->trainingPeriod();

        if ($period === null) {
            return $this->emptySummary();
        }

        [$start, $end] = $period;

        $studentCompany->loadMissing([
            'attendances',
            'branch.workingHours',
            'leaveRequests',
        ]);

        $workingDates = $this->workingDates($studentCompany, $start, $end);
        $attendanceDates = $this->attendanceDates($studentCompany)->intersect($workingDates)->values();
        $leaveRequestDates = $this->leaveRequestDates($studentCompany, $start, $end)->intersect($workingDates)->values();
        $approvedLeaveDates = $this->leaveRequestDates($studentCompany, $start, $end, approvedOnly: true)
            ->intersect($workingDates)
            ->values();

        $actualAbsenceDates = $workingDates->diff($attendanceDates)->values();
        $excusedAbsenceDates = $actualAbsenceDates->intersect($approvedLeaveDates)->values();
        $unexcusedAbsenceDates = $actualAbsenceDates->diff($approvedLeaveDates)->values();

        return [
            'training_start' => $start->toDateString(),
            'training_end' => $end->toDateString(),
            'required_working_days' => $workingDates->count(),
            'scheduled_training_days' => $workingDates->count(),
            'attendance_days' => $attendanceDates->count(),
            'total_absence_days' => $excusedAbsenceDates->count() + $unexcusedAbsenceDates->count(),
            'excused_absence_days' => $excusedAbsenceDates->count(),
            'unexcused_absence_days' => $unexcusedAbsenceDates->count(),
            'actual_absence_days' => $actualAbsenceDates->count(),
            'leave_request_days' => $leaveRequestDates->count(),
        ];
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

    private function workingDates(StudentCompany $studentCompany, Carbon $start, Carbon $end): Collection
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

        return collect(CarbonPeriod::create($start, $end))
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

    private function weekDayValue(Carbon $date): int
    {
        return (($date->dayOfWeek + 1) % 7) + 1;
    }

    private function emptySummary(): array
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
        ];
    }
}
