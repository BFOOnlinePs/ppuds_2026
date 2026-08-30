<?php

namespace Modules\Core\Exports;

use DateTimeInterface;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Sign-in / sign-out / failed-attempt rows, with the request details the
 * AuthActivitySubscriber records lifted out of the properties bag.
 */
class AuthActivityLogExport implements FromGenerator, ShouldAutoSize, WithHeadings
{
    public function __construct(protected Builder $query) {}

    public function headings(): array
    {
        return [
            __('Date'),
            __('Event'),
            __('Description'),
            __('User'),
            __('Email'),
            __('Login Identifier'),
            __('IP Address'),
            __('Browser'),
            __('Platform'),
            __('Guard'),
            __('User Agent'),
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
        $properties = $this->properties($activity);

        return [
            $this->dateTimeValue($activity->created_at),
            (string) ($activity->event ? __($activity->event) : '---'),
            __((string) $activity->description),
            (string) ($activity->causer?->name ?? '---'),
            (string) ($activity->causer?->email ?? '---'),
            (string) ($properties['login'] ?? '---'),
            (string) ($properties['ip'] ?? '---'),
            (string) ($properties['browser'] ?? '---'),
            (string) ($properties['platform'] ?? '---'),
            (string) ($properties['guard'] ?? '---'),
            (string) ($properties['user_agent'] ?? '---'),
        ];
    }

    /** @return array<string, mixed> */
    protected function properties(Model $activity): array
    {
        $properties = $activity->properties;

        return $properties instanceof Collection ? $properties->all() : (array) $properties;
    }

    protected function dateTimeValue(mixed $value): string
    {
        return $value instanceof DateTimeInterface ? $value->format('Y-m-d H:i:s') : (string) $value;
    }
}
