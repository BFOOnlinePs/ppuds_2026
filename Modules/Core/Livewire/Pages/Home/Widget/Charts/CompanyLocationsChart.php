<?php

namespace Modules\Core\Livewire\Pages\Home\Widget\Charts;

use Modules\PPUDS\Entities\StudentCompany;

class CompanyLocationsChart extends DashboardChartWidget
{
    protected static ?string $heading = 'Students In Companies By Location';

    protected static string $color = 'info';

    public static function canView(): bool
    {
        return static::canAny(['StudentCompany View List', 'Company View List', 'Branch View List']);
    }

    protected function getData(): array
    {
        $segments = $this->currentStudentCompaniesQuery()
            ->whereNotNull('company_id')
            ->with('branch.city')
            ->get(['id', 'registration_id', 'student_id', 'company_id', 'branch_id'])
            ->groupBy(fn (StudentCompany $studentCompany): string => $studentCompany->branch?->city?->name ?: __('No Location'))
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
        return 'bar';
    }
}
