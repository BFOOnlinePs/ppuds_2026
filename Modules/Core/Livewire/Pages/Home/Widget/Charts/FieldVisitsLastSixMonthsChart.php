<?php

namespace Modules\Core\Livewire\Pages\Home\Widget\Charts;

class FieldVisitsLastSixMonthsChart extends DashboardChartWidget
{
    protected static ?string $heading = 'Field Visits Last Six Months';

    protected static string $color = 'gray';

    public static function canView(): bool
    {
        return static::canAny(['FieldVisit View List']);
    }

    protected function getData(): array
    {
        $fieldVisits = $this->scopedFieldVisitsQuery();
        $months = $this->monthStarts(6);

        return [
            'datasets' => [
                [
                    'label' => __('Field Visits'),
                    'data' => collect($months)
                        ->map(fn ($month) => (clone $fieldVisits)
                            ->whereDate('visit_date', '>=', $month->toDateString())
                            ->whereDate('visit_date', '<=', $month->copy()->endOfMonth()->toDateString())
                            ->count())
                        ->all(),
                    'backgroundColor' => '#0891b2',
                ],
            ],
            'labels' => collect($months)->map(fn ($month) => $this->monthLabel($month))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
