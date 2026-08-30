<?php

namespace Modules\PPUDS\Exports;

use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\Major;
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Entities\StudentProfile;
use Modules\PPUDS\Enums\CompanyStatus;
use Modules\PPUDS\Settings\GeneralSettings;

class CompaniesExport implements FromGenerator, ShouldAutoSize, WithHeadings
{
    /**
     * company id => "Computer Science: 3, Accounting: 2", built in one grouped
     * query instead of per row.
     *
     * @var array<int, string>
     */
    protected array $majorBreakdowns = [];

    public function __construct(protected Builder $query) {}

    public function headings(): array
    {
        return [
            __('Name'),
            __('Company Category'),
            __('Supervisors'),
            __('Current Semester Students'),
            __('Students Per Major'),
            __('Company Status'),
        ];
    }

    public function generator(): Generator
    {
        $query = clone $this->query;

        $query->with(['category.translations', 'branches.supervisors'])
            ->withCurrentStudentsCount();

        $this->majorBreakdowns = $this->loadMajorBreakdowns();

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
            $this->majorBreakdowns[(int) $company->id] ?? '---',
            $this->statusLabel($company->status),
        ];
    }

    /**
     * Counts this semester's students per major for every company in the
     * export, matching the scope of `withCurrentStudentsCount()` so the two
     * columns always agree.
     *
     * @return array<int, string>
     */
    protected function loadMajorBreakdowns(): array
    {
        $companyIds = (clone $this->query)->reorder()->pluck('id')->all();

        if ($companyIds === []) {
            return [];
        }

        $settings = app(GeneralSettings::class);
        $studentCompanies = (new StudentCompany)->getTable();
        $registrations = (new Registration)->getTable();
        $profiles = (new StudentProfile)->getTable();

        $counts = DB::table($studentCompanies)
            ->join($registrations, "{$registrations}.id", '=', "{$studentCompanies}.registration_id")
            ->leftJoin($profiles, "{$profiles}.user_id", '=', "{$studentCompanies}.student_id")
            ->whereIn("{$studentCompanies}.company_id", $companyIds)
            ->whereNull("{$studentCompanies}.deleted_at")
            ->whereNull("{$registrations}.deleted_at")
            ->where("{$registrations}.semester", $settings->semester_type?->value)
            ->where("{$registrations}.year", $settings->year)
            ->groupBy("{$studentCompanies}.company_id", "{$profiles}.major_id")
            ->select([
                "{$studentCompanies}.company_id",
                "{$profiles}.major_id",
                DB::raw("COUNT(DISTINCT {$studentCompanies}.student_id) as students_count"),
            ])
            ->get();

        $majorNames = $this->majorNames($counts->pluck('major_id')->filter()->unique()->all());

        return $counts
            ->groupBy('company_id')
            ->map(function ($rows) use ($majorNames): string {
                return $rows
                    ->sortByDesc('students_count')
                    ->map(fn ($row): string => sprintf(
                        '%s: %d',
                        $majorNames[(int) $row->major_id] ?? __('Unspecified'),
                        (int) $row->students_count,
                    ))
                    ->implode(', ');
            })
            ->mapWithKeys(fn (string $breakdown, $companyId): array => [(int) $companyId => $breakdown])
            ->all();
    }

    /** @return array<int, string> */
    protected function majorNames(array $majorIds): array
    {
        if ($majorIds === []) {
            return [];
        }

        return Major::query()
            ->with('translations')
            ->whereKey($majorIds)
            ->get()
            ->mapWithKeys(fn (Major $major): array => [(int) $major->id => (string) $major->name])
            ->all();
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
