<?php

namespace Modules\PPUDS\Exports;

use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\Major;

/**
 * صف لكل شركة بعدد طلاب كل تخصص والمجموع، ثم صف الإجمالي، كما في صفحة الإحصائيات.
 */
class CompanyStatisticsExport implements FromGenerator, ShouldAutoSize, WithHeadings
{
    public function __construct(
        protected Builder $query,
        protected Collection $majors,
    ) {}

    public function headings(): array
    {
        return [
            '#',
            __('Company'),
            ...$this->majors->map(fn (Major $major): string => (string) $major->name)->all(),
            __('Total'),
        ];
    }

    public function generator(): Generator
    {
        $totals = array_fill(0, $this->majors->count() + 1, 0);
        $index = 0;

        foreach ((clone $this->query)->lazy(500) as $company) {
            $counts = $this->countsFor($company);

            foreach ($counts as $position => $count) {
                $totals[$position] += $count;
            }

            yield [(string) ++$index, (string) ($company->name ?? '---'), ...array_map('strval', $counts)];
        }

        yield ['', __('Total'), ...array_map('strval', $totals)];
    }

    /** @return array<int, int> عدد كل تخصص بترتيب الأعمدة، ثم المجموع */
    protected function countsFor(Company $company): array
    {
        return [
            ...$this->majors->map(fn (Major $major): int => (int) $company->{"major_{$major->id}_count"})->all(),
            (int) $company->students_count,
        ];
    }
}
