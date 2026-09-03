<?php

namespace Modules\Core\Livewire\Pages\Home\Widget\Charts;

use Modules\PPUDS\Enums\TrainingStatus;

class TrainingStatusChart extends DashboardChartWidget
{
    protected static ?string $heading = 'Training Status Distribution';

    protected static string $color = 'success';

    public static function canView(): bool
    {
        return static::canAny(['StudentCompany View List']);
    }

    protected function getData(): array
    {
        $studentCompanies = $this->scopedStudentCompaniesQuery();
        $statuses = collect(TrainingStatus::cases());

        return [
            'datasets' => [
                [
                    'label' => __('Student Companies'),
                    'data' => $statuses
                        ->map(fn (TrainingStatus $status) => (clone $studentCompanies)->where('status', $status->value)->count())
                        ->all(),
                    'backgroundColor' => $this->chartColors($statuses->count()),
                ],
            ],
            'labels' => $statuses->map(fn (TrainingStatus $status) => $status->getLabel())->all(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
