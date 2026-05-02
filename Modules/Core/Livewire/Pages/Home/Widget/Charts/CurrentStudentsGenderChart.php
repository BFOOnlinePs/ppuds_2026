<?php

namespace Modules\Core\Livewire\Pages\Home\Widget\Charts;

use Illuminate\Database\Eloquent\Builder;
use Modules\PPUDS\Enums\StudentGender;

class CurrentStudentsGenderChart extends DashboardChartWidget
{
    protected static ?string $heading = 'Current Semester Students By Gender';

    protected static string $color = 'info';

    public static function canView(): bool
    {
        return static::canAny(['Student View List']);
    }

    protected function getData(): array
    {
        $registrations = $this->currentRegistrationsQuery();
        $genders = collect(StudentGender::cases());

        return [
            'datasets' => [
                [
                    'label' => __('Students'),
                    'data' => $genders
                        ->map(fn (StudentGender $gender) => $this->countStudentsByGender($registrations, $gender))
                        ->all(),
                    'backgroundColor' => $this->chartColors($genders->count()),
                ],
            ],
            'labels' => $genders->map(fn (StudentGender $gender) => $gender->getLabel())->all(),
        ];
    }

    private function countStudentsByGender(Builder $query, StudentGender $gender): int
    {
        return (clone $query)
            ->whereHas('student.studentProfile', fn (Builder $profileQuery) => $profileQuery->where('gender', $gender->value))
            ->distinct('student_id')
            ->count('student_id');
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
