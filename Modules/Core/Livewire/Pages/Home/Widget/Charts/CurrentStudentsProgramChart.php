<?php

namespace Modules\Core\Livewire\Pages\Home\Widget\Charts;

use Illuminate\Database\Eloquent\Builder;
use Modules\PPUDS\Entities\Major;

class CurrentStudentsProgramChart extends DashboardChartWidget
{
    protected static ?string $heading = 'Current Semester Students By Programs';

    protected static string $color = 'primary';

    public static function canView(): bool
    {
        return static::canAny(['Student View List', 'Registration View List']);
    }

    protected function getData(): array
    {
        $registrations = $this->currentRegistrationsQuery();
        $segments = Major::query()
            ->orderBy('id')
            ->get()
            ->map(fn (Major $major): array => [
                'label' => $major->name ?: __('No Program'),
                'count' => $this->countStudentsByMajor($registrations, $major->id),
            ])
            ->filter(fn (array $segment): bool => $segment['count'] > 0)
            ->values();

        $studentsWithoutProgram = $this->countStudentsWithoutProgram($registrations);

        if ($studentsWithoutProgram > 0) {
            $segments->push([
                'label' => __('No Program'),
                'count' => $studentsWithoutProgram,
            ]);
        }

        return [
            'datasets' => [
                [
                    'label' => __('Students'),
                    'data' => $segments->pluck('count')->all(),
                    'backgroundColor' => $this->chartColors($segments->count()),
                ],
            ],
            'labels' => $segments->pluck('label')->all(),
        ];
    }

    private function countStudentsByMajor(Builder $query, int $majorId): int
    {
        return (clone $query)
            ->whereHas('student.studentProfile', fn (Builder $profileQuery) => $profileQuery->where('major_id', $majorId))
            ->distinct('student_id')
            ->count('student_id');
    }

    private function countStudentsWithoutProgram(Builder $query): int
    {
        return (clone $query)
            ->where(function (Builder $query): void {
                $query
                    ->whereDoesntHave('student.studentProfile')
                    ->orWhereHas('student.studentProfile', fn (Builder $profileQuery) => $profileQuery->whereNull('major_id'));
            })
            ->distinct('student_id')
            ->count('student_id');
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
