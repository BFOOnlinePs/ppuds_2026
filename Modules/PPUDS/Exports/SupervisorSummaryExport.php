<?php

namespace Modules\PPUDS\Exports;

use DateTimeInterface;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Modules\Core\Entities\User;

/**
 * One aggregated row per supervisor, matching the supervisor summary report.
 * Expects the aggregate columns added by SupervisorReportService.
 */
class SupervisorSummaryExport implements FromGenerator, ShouldAutoSize, WithHeadings
{
    public function __construct(protected Builder $query) {}

    public function headings(): array
    {
        return [
            __('Supervisor'),
            __('Email'),
            __('Phone'),
            __('Roles'),
            __('Supervised Students Count'),
            __('Trainings'),
            __('Companies'),
            __('Field Visits Count'),
            __('Duration (Mins)'),
            __('Visited Students'),
            __('Last Field Visit'),
            __('Activities Count'),
            __('Last Activity'),
        ];
    }

    public function generator(): Generator
    {
        $query = clone $this->query;

        $query->with('roles');

        foreach ($query->lazy(200) as $supervisor) {
            yield $this->rowFor($supervisor);
        }
    }

    protected function rowFor(User $supervisor): array
    {
        return [
            (string) ($supervisor->name ?? '---'),
            (string) ($supervisor->email ?? '---'),
            (string) ($supervisor->phone ?? '---'),
            $supervisor->roles->pluck('name')->map(fn (string $role): string => __($role))->implode(', '),
            (string) ((int) $supervisor->supervised_students_count),
            (string) ((int) $supervisor->supervised_trainings_count),
            (string) ((int) $supervisor->supervised_companies_count),
            (string) ((int) $supervisor->field_visits_count),
            (string) ((int) $supervisor->field_visit_minutes),
            (string) ((int) $supervisor->visited_students_count),
            $this->dateValue($supervisor->last_field_visit_at),
            (string) ((int) $supervisor->activities_count),
            $this->dateTimeValue($supervisor->last_activity_at),
        ];
    }

    protected function dateValue(mixed $value): string
    {
        if (blank($value)) {
            return '---';
        }

        return $value instanceof DateTimeInterface
            ? $value->format('Y-m-d')
            : substr((string) $value, 0, 10);
    }

    protected function dateTimeValue(mixed $value): string
    {
        if (blank($value)) {
            return '---';
        }

        return $value instanceof DateTimeInterface
            ? $value->format('Y-m-d H:i')
            : substr((string) $value, 0, 16);
    }
}
