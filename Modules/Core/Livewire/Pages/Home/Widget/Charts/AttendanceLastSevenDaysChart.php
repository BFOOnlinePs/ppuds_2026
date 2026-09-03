<?php

namespace Modules\Core\Livewire\Pages\Home\Widget\Charts;

class AttendanceLastSevenDaysChart extends DashboardChartWidget
{
    protected static ?string $heading = 'Attendance Last Seven Days';

    protected static string $color = 'warning';

    public static function canView(): bool
    {
        return static::canAny(['StudentAttendance View List']);
    }

    protected function getData(): array
    {
        $attendance = $this->scopedAttendanceQuery()->whereNotNull('check_in');
        $days = $this->lastDays(7);

        return [
            'datasets' => [
                [
                    'label' => __('Attendance And Departure Log'),
                    'data' => collect($days)
                        ->map(fn ($date) => (clone $attendance)->whereDate('attendance_date', $date->toDateString())->count())
                        ->all(),
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.14)',
                    'fill' => true,
                    'tension' => 0.35,
                ],
            ],
            'labels' => collect($days)->map(fn ($date) => $this->dayLabel($date))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
