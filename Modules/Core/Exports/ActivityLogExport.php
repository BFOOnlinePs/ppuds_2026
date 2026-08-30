<?php

namespace Modules\Core\Exports;

use DateTimeInterface;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Activity-log rows for any activity query, flattening the recorded property
 * changes into one readable column.
 */
class ActivityLogExport implements FromGenerator, ShouldAutoSize, WithHeadings
{
    public function __construct(protected Builder $query) {}

    public function headings(): array
    {
        return [
            __('Date'),
            __('Log Name'),
            __('Event'),
            __('Description'),
            __('Subject Type'),
            __('Subject Id'),
            __('Causer'),
            __('Changes'),
        ];
    }

    public function generator(): Generator
    {
        $query = clone $this->query;

        $query->with('causer');

        foreach ($query->lazy(500) as $activity) {
            yield $this->rowFor($activity);
        }
    }

    protected function rowFor(Model $activity): array
    {
        return [
            $this->dateTimeValue($activity->created_at),
            (string) ($activity->log_name ?? '---'),
            (string) ($activity->event ?? '---'),
            __((string) $activity->description),
            $activity->subject_type ? __(class_basename($activity->subject_type)) : '---',
            (string) ($activity->subject_id ?? '---'),
            (string) ($activity->causer?->name ?? '---'),
            $this->changesValue($activity),
        ];
    }

    /** "field: old -> new" pairs, so the sheet stays readable without JSON. */
    protected function changesValue(Model $activity): string
    {
        $properties = $activity->properties;
        $properties = $properties instanceof \Illuminate\Support\Collection ? $properties->all() : (array) $properties;

        $attributes = (array) ($properties['attributes'] ?? []);
        $old = (array) ($properties['old'] ?? []);

        if ($attributes === []) {
            return $properties === []
                ? '---'
                : (json_encode($properties, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '---');
        }

        $changes = [];

        foreach ($attributes as $key => $value) {
            $before = $this->scalarValue($old[$key] ?? null);
            $after = $this->scalarValue($value);

            $changes[] = array_key_exists($key, $old)
                ? "{$key}: {$before} → {$after}"
                : "{$key}: {$after}";
        }

        return implode(' | ', $changes);
    }

    protected function scalarValue(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        if (is_bool($value)) {
            return $value ? __('Yes') : __('No');
        }

        return blank($value) ? '—' : (string) $value;
    }

    protected function dateTimeValue(mixed $value): string
    {
        return $value instanceof DateTimeInterface ? $value->format('Y-m-d H:i') : (string) $value;
    }
}
