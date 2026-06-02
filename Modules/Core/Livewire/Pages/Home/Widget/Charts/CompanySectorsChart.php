<?php

namespace Modules\Core\Livewire\Pages\Home\Widget\Charts;

use Modules\PPUDS\Entities\StudentCompany;

class CompanySectorsChart extends DashboardChartWidget
{
    protected static ?string $heading = 'Students In Companies By Sector';

    protected static string $color = 'success';

    public static function canView(): bool
    {
        return static::canAny(['StudentCompany View List', 'Company View List']);
    }

    protected function getData(): array
    {
        $segments = $this->currentStudentCompaniesQuery()
            ->whereNotNull('company_id')
            ->with('company.category')
            ->get(['id', 'registration_id', 'student_id', 'company_id'])
            ->groupBy(fn (StudentCompany $studentCompany): string => $studentCompany->company?->category?->name ?: __('No Sector'))
            ->map(fn ($studentCompanies): int => $studentCompanies->unique('student_id')->count())
            ->sortDesc();

        return [
            'datasets' => [
                [
                    'label' => __('Students'),
                    'data' => $segments->values()->all(),
                    'backgroundColor' => $this->chartColors($segments->count()),
                ],
            ],
            'labels' => $segments->keys()->all(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
