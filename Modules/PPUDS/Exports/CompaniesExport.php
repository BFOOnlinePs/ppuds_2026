<?php

namespace Modules\PPUDS\Exports;

use Generator;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Enums\CompanyStatus;

class CompaniesExport implements FromGenerator, ShouldAutoSize, WithHeadings
{
    public function __construct(protected Builder $query) {}

    public function headings(): array
    {
        return [
            __('Name'),
            __('Company Category'),
            __('Supervisors'),
            __('Current Semester Students'),
            __('Company Status'),
        ];
    }

    public function generator(): Generator
    {
        $query = clone $this->query;

        $query->with(['category.translations', 'branches.supervisors'])
            ->withCurrentStudentsCount();

        foreach ($query->lazy(500) as $company) {
            yield $this->rowFor($company);
        }
    }

    protected function rowFor(Company $company): array
    {
        return [
            (string) $company->name,
            (string) ($company->category?->name ?? '---'),
            implode(', ', $company->companySupervisorNames()),
            (string) ($company->current_students_count ?? 0),
            $this->statusLabel($company->status),
        ];
    }

    protected function statusLabel(mixed $status): string
    {
        if ($status instanceof CompanyStatus) {
            return (string) $status->getLabel();
        }

        if (is_numeric($status)) {
            return (string) (CompanyStatus::tryFrom((int) $status)?->getLabel() ?? $status);
        }

        return (string) ($status ?: '---');
    }
}
