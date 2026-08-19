<?php

namespace Modules\PPUDS\Exports;

use DateTimeInterface;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Modules\PPUDS\Entities\FieldVisit;

class FieldVisitsExport implements FromGenerator, ShouldAutoSize, WithHeadings
{
    public function __construct(protected Builder $query) {}

    public function headings(): array
    {
        return [
            __('Student'),
            __('Email'),
            __('Company'),
            __('Supervisor'),
            __('Visiting Place'),
            __('Visit Date'),
            __('Visit Time'),
            __('Duration (Mins)'),
            __('Notes'),
            __('Created At'),
        ];
    }

    public function generator(): Generator
    {
        $query = clone $this->query;

        $query->with([
            'studentCompany.registration.student',
            'studentCompany.company.translations',
            'supervisor',
        ]);

        foreach ($query->lazy(500) as $fieldVisit) {
            yield $this->rowFor($fieldVisit);
        }
    }

    protected function rowFor(FieldVisit $fieldVisit): array
    {
        $student = $fieldVisit->studentCompany?->registration?->student;

        return [
            (string) $student?->name,
            (string) $student?->email,
            (string) $fieldVisit->studentCompany?->company?->name,
            (string) $fieldVisit->supervisor?->name,
            (string) $fieldVisit->visiting_place,
            $this->dateValue($fieldVisit->visit_date),
            (string) $fieldVisit->visit_time,
            (string) $fieldVisit->visit_duration,
            (string) $fieldVisit->notes,
            $this->dateTimeValue($fieldVisit->created_at),
        ];
    }

    protected function dateValue(mixed $value): string
    {
        return $value instanceof DateTimeInterface ? $value->format('Y-m-d') : (string) $value;
    }

    protected function dateTimeValue(mixed $value): string
    {
        return $value instanceof DateTimeInterface ? $value->format('Y-m-d H:i') : (string) $value;
    }
}
