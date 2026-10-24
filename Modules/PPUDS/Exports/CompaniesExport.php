<?php

namespace Modules\PPUDS\Exports;

use Closure;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Modules\Branch\Entities\Branch;
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
            __('About Company'),
            __('Website'),
            __('Contact Person'),
            __('Contact Information'),
            __('Company Category'),
            __('Company Status'),
            __('Branches'),
            __('Email'),
            __('Phone Number'),
            __('Company Manager Name'),
            __('Company Manager Phone'),
            __('Country'),
            __('City'),
            __('Location'),
            __('Working Hours'),
            __('Departments'),
            __('Supervisors'),
            __('Current Semester Students'),
            __('Students Per Major'),
            __('Created By'),
            __('Created At'),
        ];
    }

    public function generator(): Generator
    {
        $query = clone $this->query;

        $query->with([
            'translations',
            'category.translations',
            'createdBy',
            'branches.translations',
            'branches.country.translations',
            'branches.city.translations',
            'branches.workingHours',
            'branches.departments.translations',
            'branches.supervisors',
        ])
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
            (string) ($company->description ?: '---'),
            (string) ($company->website ?: '---'),
            (string) ($company->contact_person ?: '---'),
            (string) ($company->contact_info ?: '---'),
            (string) ($company->category?->name ?? '---'),
            $this->statusLabel($company->status),
            $this->branchContacts($company, 'name'),
            $this->branchContacts($company, 'email'),
            $this->branchContacts($company, 'phone'),
            $this->branchContacts($company, 'manager_name'),
            $this->branchContacts($company, 'manager_phone'),
            $this->branchContacts($company, fn (Branch $branch) => $branch->country?->name),
            $this->branchContacts($company, fn (Branch $branch) => $branch->city?->name),
            $this->branchContacts($company, fn (Branch $branch) => filled($branch->latitude) && filled($branch->longitude)
                ? "{$branch->latitude}, {$branch->longitude}"
                : null),
            $this->perBranch($company, fn (Branch $branch): string => $this->workingHours($branch)),
            $this->perBranch($company, fn (Branch $branch): string => $this->departments($branch)),
            implode(', ', $company->companySupervisorNames()),
            (string) ($company->current_students_count ?? 0),
            $this->majorBreakdowns[(int) $company->id] ?? '---',
            (string) ($company->createdBy?->name ?? '---'),
            (string) ($company->created_at?->format('Y-m-d H:i') ?? '---'),
        ];
    }

    /**
     * A company's contact details are stored on its branches, so list every
     * distinct value from the already eager-loaded branches.
     */
    protected function branchContacts(Company $company, string|Closure $field): string
    {
        $values = $company->branches
            ->map(fn (Branch $branch) => $field instanceof Closure ? $field($branch) : $branch->{$field})
            ->filter(fn ($value): bool => filled($value))
            ->unique()
            ->implode(', ');

        return $values !== '' ? $values : '---';
    }

    /**
     * Values that only make sense per branch (working hours, departments) are
     * prefixed with the branch name when the company has more than one.
     */
    protected function perBranch(Company $company, callable $format): string
    {
        $multiple = $company->branches->count() > 1;

        $values = $company->branches
            ->map(function (Branch $branch) use ($format, $multiple): ?string {
                $value = $format($branch);

                if ($value === '') {
                    return null;
                }

                return $multiple ? "{$branch->name}: {$value}" : $value;
            })
            ->filter()
            ->implode(' | ');

        return $values !== '' ? $values : '---';
    }

    protected function workingHours(Branch $branch): string
    {
        return $branch->workingHours
            ->sortBy(fn ($hour): int => (int) ($hour->day?->value ?? 0))
            ->map(fn ($hour): string => sprintf(
                '%s %s',
                $hour->day?->getLabel() ?? '',
                $hour->is_closed
                    ? __('Closed')
                    : ($hour->start_time?->format('H:i') ?? '--').' - '.($hour->end_time?->format('H:i') ?? '--'),
            ))
            ->implode(', ');
    }

    protected function departments(Branch $branch): string
    {
        return $branch->departments
            ->unique('id')
            ->map(function ($department) use ($branch): string {
                $supervisors = $branch->supervisors
                    ->filter(fn ($user): bool => (int) $user->pivot->company_department_id === (int) $department->id)
                    ->pluck('name')
                    ->filter()
                    ->unique()
                    ->implode(' / ');

                return $supervisors !== ''
                    ? "{$department->name} ({$supervisors})"
                    : (string) $department->name;
            })
            ->implode(', ');
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
