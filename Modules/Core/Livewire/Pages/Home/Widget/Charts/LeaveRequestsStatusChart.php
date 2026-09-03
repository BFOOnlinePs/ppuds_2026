<?php

namespace Modules\Core\Livewire\Pages\Home\Widget\Charts;

use Modules\PPUDS\Enums\LeaveRequestStatus;

class LeaveRequestsStatusChart extends DashboardChartWidget
{
    protected static ?string $heading = 'Leave Requests Status';

    protected static string $color = 'danger';

    public static function canView(): bool
    {
        return static::canAny(['LeaveRequest View List']);
    }

    protected function getData(): array
    {
        $leaveRequests = $this->scopedLeaveRequestsQuery();
        $statuses = collect(LeaveRequestStatus::cases());

        return [
            'datasets' => [
                [
                    'label' => __('Company Status'),
                    'data' => $statuses
                        ->map(fn (LeaveRequestStatus $status) => (clone $leaveRequests)->where('company_approval', $status->value)->count())
                        ->all(),
                    'backgroundColor' => '#2563eb',
                ],
                [
                    'label' => __('University Status'),
                    'data' => $statuses
                        ->map(fn (LeaveRequestStatus $status) => (clone $leaveRequests)->where('university_approval', $status->value)->count())
                        ->all(),
                    'backgroundColor' => '#16a34a',
                ],
            ],
            'labels' => $statuses->map(fn (LeaveRequestStatus $status) => $status->getLabel())->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
