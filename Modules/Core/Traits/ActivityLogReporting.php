<?php

namespace Modules\Core\Traits;

use Filament\Tables\Filters\Indicator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Core\Entities\User;
use Spatie\Activitylog\Models\Activity;

/**
 * Shared plumbing for the activity-log report pages: reading the configured
 * activity model, turning the properties bag into something displayable, and
 * building the filter option lists.
 */
trait ActivityLogReporting
{
    /** @return class-string<Model> */
    protected function activityModel(): string
    {
        return config('activitylog.activity_model', Activity::class);
    }

    protected function activityTable(): string
    {
        return config('activitylog.table_name', 'activity_log');
    }

    /** Overridden by pages that report on a subset, such as the auth log. */
    protected function activityQuery(): Builder
    {
        return $this->activityModel()::query();
    }

    /** @return array<string, mixed> */
    protected function activityProperties(Model $activity): array
    {
        $properties = $activity->properties;

        return $properties instanceof Collection ? $properties->all() : (array) $properties;
    }

    /** @return array<string, string> */
    protected function flattenProperties(Model $activity, string $key): array
    {
        return collect((array) ($this->activityProperties($activity)[$key] ?? []))
            ->map(fn (mixed $value): string => $this->readableValue($value))
            ->all();
    }

    /**
     * Everything the subscriber recorded about the request itself — the keys
     * that are not the model's own before/after attributes.
     *
     * @return array<string, string>
     */
    protected function requestProperties(Model $activity): array
    {
        return collect($this->activityProperties($activity))
            ->except(['attributes', 'old'])
            ->map(fn (mixed $value): string => $this->readableValue($value))
            ->all();
    }

    /** "field: old → new" pairs, short enough for a table cell. */
    protected function changesSummary(Model $activity): string
    {
        $attributes = $this->flattenProperties($activity, 'attributes');
        $old = $this->flattenProperties($activity, 'old');

        if ($attributes === []) {
            return '—';
        }

        $changes = [];

        foreach ($attributes as $key => $value) {
            $changes[] = array_key_exists($key, $old)
                ? "{$key}: {$old[$key]} → {$value}"
                : "{$key}: {$value}";
        }

        return implode(' | ', $changes);
    }

    protected function readableValue(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        if (is_bool($value)) {
            return $value ? __('Yes') : __('No');
        }

        return blank($value) ? '—' : (string) $value;
    }

    protected function eventColor(?string $event): string
    {
        return match ($event) {
            'created', 'login' => 'success',
            'updated', 'logout' => 'warning',
            'deleted', 'failed_login', 'lockout' => 'danger',
            'token_refreshed' => 'info',
            default => 'gray',
        };
    }

    /** @return array<string, string> */
    protected function logNameOptions(): array
    {
        return $this->activityQuery()
            ->select('log_name')
            ->whereNotNull('log_name')
            ->distinct()
            ->orderBy('log_name')
            ->pluck('log_name', 'log_name')
            ->map(fn (string $name): string => __($name))
            ->all();
    }

    /** @return array<string, string> */
    protected function eventOptions(): array
    {
        return $this->activityQuery()
            ->select('event')
            ->whereNotNull('event')
            ->distinct()
            ->orderBy('event')
            ->pluck('event', 'event')
            ->map(fn (string $event): string => __($event))
            ->all();
    }

    /** @return array<string, string> */
    protected function subjectTypeOptions(): array
    {
        return $this->activityQuery()
            ->select('subject_type')
            ->whereNotNull('subject_type')
            ->distinct()
            ->orderBy('subject_type')
            ->pluck('subject_type', 'subject_type')
            ->map(fn (string $type): string => __(class_basename($type)))
            ->all();
    }

    /** Only users who actually appear in the log, so the list stays short. */
    protected function causerOptions(): array
    {
        $causerIds = $this->activityQuery()
            ->where('causer_type', (new User)->getMorphClass())
            ->whereNotNull('causer_id')
            ->distinct()
            ->pluck('causer_id');

        return User::query()
            ->whereKey($causerIds)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    protected function applyDateRange(Builder $query, array $data, string $column = 'created_at'): Builder
    {
        return $query
            ->when(
                $data['from'] ?? null,
                fn (Builder $query, $date): Builder => $query->whereDate($column, '>=', Carbon::parse($date)->toDateString())
            )
            ->when(
                $data['until'] ?? null,
                fn (Builder $query, $date): Builder => $query->whereDate($column, '<=', Carbon::parse($date)->toDateString())
            );
    }

    protected function dateRangeIndicators(array $data): array
    {
        $indicators = [];

        if (filled($data['from'] ?? null)) {
            $indicators[] = Indicator::make(__('From Date').': '.Carbon::parse($data['from'])->toDateString())
                ->removeField('from');
        }

        if (filled($data['until'] ?? null)) {
            $indicators[] = Indicator::make(__('Until Date').': '.Carbon::parse($data['until'])->toDateString())
                ->removeField('until');
        }

        return $indicators;
    }
}
