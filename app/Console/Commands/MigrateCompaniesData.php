<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Branch\Entities\Branch;
use Modules\Branch\Entities\BranchWorkingHour;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\CompanyCategory;
use Modules\PPUDS\Entities\CompanyDepartment;

class MigrateCompaniesData extends Command
{
    protected $signature = 'migrate:companies {--update-existing : Update companies that already exist from the old database.}';

    protected $description = 'Migrate companies and transfer real managers using c_manager_id without duplicating existing imports.';

    private const DEFAULT_CATEGORY_NAME_AR = 'تصنيف عام';

    private const DEFAULT_CATEGORY_NAME_EN = 'General Category';

    private ?bool $companiesHaveOldCompanyIdColumn = null;

    public function handle()
    {
        $this->info('Starting companies migration...');

        $categoryIds = $this->syncCompanyCategories();
        $defaultCategory = $this->firstOrCreateDefaultCategory();

        // 1. التأكد من وجود القسم (القسم الأول) أو إنشاؤه
        $department = CompanyDepartment::whereTranslation('name', 'القسم الأول', 'ar')->first();

        if (! $department) {
            $department = new CompanyDepartment(['created_by' => 1]);
            $department->fill([
                'ar' => ['name' => 'القسم الأول'],
                'en' => ['name' => 'First Department'],
            ]);
            $department->save();
        }

        $totalCompanies = DB::connection('old_db')->table('companies')->count();
        $bar = $this->output->createProgressBar($totalCompanies);
        $bar->start();
        $stats = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
        ];

        DB::connection('old_db')
            ->table('companies')
            ->select([
                'c_id',
                'c_name',
                'c_english_name',
                'c_description',
                'c_english_description',
                'c_website',
                'c_category_id',
                'c_manager_id',
            ])
            ->chunkById(500, function (Collection $oldCompanies) use ($department, $categoryIds, $defaultCategory, $bar, &$stats) {
                $managers = $this->getOldManagers($oldCompanies);
                $managerIdsByEmail = $this->upsertManagers($oldCompanies, $managers);

                foreach ($oldCompanies as $oldCompany) {
                    $result = DB::transaction(function () use ($oldCompany, $department, $categoryIds, $defaultCategory, $managers, $managerIdsByEmail) {
                        $oldManager = ! empty($oldCompany->c_manager_id)
                            ? $managers->get($oldCompany->c_manager_id)
                            : null;

                        $managerId = $managerIdsByEmail[$this->managerEmail($oldCompany, $oldManager)] ?? 1;
                        $categoryId = $categoryIds[$oldCompany->c_category_id] ?? $defaultCategory->id;

                        return $this->syncCompany($oldCompany, $categoryId, $managerId, $department);
                    });

                    $stats[$result]++;
                    $bar->advance();
                }
            }, 'c_id');

        $bar->finish();

        $this->newLine();
        $this->info(sprintf(
            'Migration completed successfully! Created: %d, Updated: %d, Skipped existing: %d',
            $stats['created'],
            $stats['updated'],
            $stats['skipped'],
        ));

        return self::SUCCESS;
    }

    private function syncCompany(object $oldCompany, int $categoryId, int $managerId, CompanyDepartment $department): string
    {
        $company = $this->findExistingCompany($oldCompany);
        $alreadyExists = $company instanceof Company;

        if (! $alreadyExists) {
            $company = new Company;
        } elseif ($company->trashed()) {
            $company->restore();
        }

        if (! $alreadyExists || $this->option('update-existing')) {
            $company->fill($this->companyAttributes($oldCompany, $categoryId, $managerId));
            $company->fill($this->companyTranslations($oldCompany));
            $company->save();
        } else {
            $this->markCompanyAsImported($company, $oldCompany);
        }

        $branch = $this->firstOrCreateMainBranch($company, $managerId);
        $this->ensureCompanyBranchData($company, $branch, $department, $managerId);

        if (! $alreadyExists) {
            return 'created';
        }

        return $this->option('update-existing') ? 'updated' : 'skipped';
    }

    private function companyAttributes(object $oldCompany, int $categoryId, int $managerId): array
    {
        $attributes = [
            'website' => $oldCompany->c_website ?? null,
            'company_category_id' => $categoryId,
            'status' => 1,
            'created_by' => $managerId,
        ];

        if ($this->companiesHaveOldCompanyId()) {
            $attributes['old_company_id'] = $oldCompany->c_id;
        }

        return $attributes;
    }

    private function companyTranslations(object $oldCompany): array
    {
        return [
            'ar' => [
                'name' => $oldCompany->c_name,
                'description' => Str::limit($oldCompany->c_description ?? '', 250, ''),
            ],
            'en' => [
                'name' => $oldCompany->c_english_name ?? $oldCompany->c_name,
                'description' => Str::limit($oldCompany->c_english_description ?? '', 250, ''),
            ],
        ];
    }

    private function findExistingCompany(object $oldCompany): ?Company
    {
        if ($this->companiesHaveOldCompanyId()) {
            $company = Company::withTrashed()
                ->where('old_company_id', $oldCompany->c_id)
                ->first();

            if ($company) {
                return $company;
            }
        }

        $company = Company::withTrashed()->find($oldCompany->c_id);

        if (
            $company
            && $this->companyCanBeMatchedToOldCompany($company, $oldCompany)
            && $this->companyNameMatchesOldCompany($company, $oldCompany)
        ) {
            return $company;
        }

        $arabicName = trim((string) $oldCompany->c_name);

        if ($arabicName !== '') {
            $company = $this->unmappedCompaniesQuery()
                ->whereTranslation('name', $arabicName, 'ar')
                ->first();

            if ($company) {
                return $company;
            }
        }

        $englishName = trim((string) ($oldCompany->c_english_name ?? ''));

        if ($englishName !== '') {
            return $this->unmappedCompaniesQuery()
                ->whereTranslation('name', $englishName, 'en')
                ->first();
        }

        return null;
    }

    private function unmappedCompaniesQuery()
    {
        return Company::withTrashed()
            ->when($this->companiesHaveOldCompanyId(), fn ($query) => $query->whereNull('old_company_id'));
    }

    private function companyCanBeMatchedToOldCompany(Company $company, object $oldCompany): bool
    {
        if (! $this->companiesHaveOldCompanyId()) {
            return true;
        }

        return blank($company->old_company_id) || (int) $company->old_company_id === (int) $oldCompany->c_id;
    }

    private function companyNameMatchesOldCompany(Company $company, object $oldCompany): bool
    {
        $arabicName = trim((string) $oldCompany->c_name);
        $englishName = trim((string) ($oldCompany->c_english_name ?? ''));

        if ($arabicName !== '' && $company->translate('ar')?->name === $arabicName) {
            return true;
        }

        return $englishName !== '' && $company->translate('en')?->name === $englishName;
    }

    private function markCompanyAsImported(Company $company, object $oldCompany): void
    {
        if (! $this->companiesHaveOldCompanyId() || filled($company->old_company_id)) {
            return;
        }

        $company->forceFill(['old_company_id' => $oldCompany->c_id])->save();
    }

    private function firstOrCreateMainBranch(Company $company, int $managerId): Branch
    {
        $branch = $company->branches()
            ->wherePivot('is_main', true)
            ->first();

        if ($branch) {
            return $branch;
        }

        $branch = $company->branches()->first();

        if ($branch) {
            return $branch;
        }

        return Branch::create([
            'status' => 1,
            'created_by' => $managerId,
            'city_id' => 1,
            'country_id' => 1,
            'ar' => [
                'name' => 'الفرع الرئيسي',
                'address' => 'العنوان الرئيسي',
            ],
            'en' => [
                'name' => 'Main Branch',
                'address' => 'Main Address',
            ],
        ]);
    }

    private function ensureCompanyBranchData(Company $company, Branch $branch, CompanyDepartment $department, int $managerId): void
    {
        $company->branches()->syncWithoutDetaching([
            $branch->id => ['is_main' => 1],
        ]);

        $this->firstOrCreateDepartmentSupervisor($branch->id, $department->id, $managerId);
        $this->firstOrCreateWorkingHours($branch->id);
    }

    private function firstOrCreateDepartmentSupervisor(int $branchId, int $departmentId, int $managerId): void
    {
        $table = config('ppuds.table_prefix').'branch_department';

        $exists = DB::table($table)
            ->where('branch_id', $branchId)
            ->where('company_department_id', $departmentId)
            ->exists();

        if ($exists) {
            return;
        }

        $now = now();

        DB::table($table)->insert([
            'branch_id' => $branchId,
            'company_department_id' => $departmentId,
            'user_id' => $managerId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function firstOrCreateWorkingHours(int $branchId): void
    {
        $table = (new BranchWorkingHour)->getTable();

        foreach ($this->workingHoursRows($branchId) as $row) {
            $exists = DB::table($table)
                ->where('branch_id', $row['branch_id'])
                ->where('day', $row['day'])
                ->exists();

            if (! $exists) {
                DB::table($table)->insert($row);
            }
        }
    }

    private function companiesHaveOldCompanyId(): bool
    {
        if ($this->companiesHaveOldCompanyIdColumn !== null) {
            return $this->companiesHaveOldCompanyIdColumn;
        }

        return $this->companiesHaveOldCompanyIdColumn = Schema::hasColumn((new Company)->getTable(), 'old_company_id');
    }

    private function syncCompanyCategories(): array
    {
        $oldCategories = DB::connection('old_db')
            ->table('companies_categories')
            ->select(['cc_id', 'cc_name'])
            ->orderBy('cc_id')
            ->get();

        $categoryIds = [];

        DB::transaction(function () use ($oldCategories, &$categoryIds) {
            foreach ($oldCategories as $oldCategory) {
                $name = filled($oldCategory->cc_name)
                    ? $oldCategory->cc_name
                    : self::DEFAULT_CATEGORY_NAME_AR;

                $category = CompanyCategory::withTrashed()->firstOrNew([
                    'id' => $oldCategory->cc_id,
                ]);

                if ($category->exists && $category->trashed()) {
                    $category->restore();
                }

                $category->created_by = $category->created_by ?: 1;
                $category->fill([
                    'ar' => ['name' => $name],
                    'en' => ['name' => $name],
                ]);
                $category->save();

                $categoryIds[$oldCategory->cc_id] = $category->id;
            }
        });

        return $categoryIds;
    }

    private function firstOrCreateDefaultCategory(): CompanyCategory
    {
        $category = CompanyCategory::whereTranslation('name', self::DEFAULT_CATEGORY_NAME_AR, 'ar')->first();

        if ($category) {
            return $category;
        }

        $category = new CompanyCategory(['created_by' => 1]);
        $category->fill([
            'ar' => ['name' => self::DEFAULT_CATEGORY_NAME_AR],
            'en' => ['name' => self::DEFAULT_CATEGORY_NAME_EN],
        ]);
        $category->save();

        return $category;
    }

    private function getOldManagers(Collection $oldCompanies): Collection
    {
        $managerIds = $oldCompanies
            ->pluck('c_manager_id')
            ->filter()
            ->unique()
            ->values();

        if ($managerIds->isEmpty()) {
            return collect();
        }

        return DB::connection('old_db')
            ->table('users')
            ->select(['u_id', 'u_username', 'name', 'email', 'u_phone1', 'u_phone2'])
            ->whereIn('u_id', $managerIds)
            ->get()
            ->keyBy('u_id');
    }

    private function upsertManagers(Collection $oldCompanies, Collection $managers): array
    {
        $now = now();
        $password = Hash::make('123456789');
        $rows = [];

        foreach ($oldCompanies as $oldCompany) {
            $oldManager = ! empty($oldCompany->c_manager_id)
                ? $managers->get($oldCompany->c_manager_id)
                : null;

            $email = $this->managerEmail($oldCompany, $oldManager);
            $managerName = $this->managerName($oldCompany, $oldManager);

            $rows[$email] = [
                'name' => $managerName,
                'name_en' => $this->managerNameEn($managerName, $oldCompany, $oldManager),
                'email' => $email,
                'phone' => $this->managerPhone($oldManager),
                'password' => $password,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        User::upsert(
            array_values($rows),
            ['email'],
            ['name', 'name_en', 'phone', 'updated_at']
        );

        $managerIdsByEmail = User::whereIn('email', array_keys($rows))
            ->pluck('id', 'email')
            ->all();

        $this->assignCompanySupervisorRole($managerIdsByEmail);

        return $managerIdsByEmail;
    }

    private function assignCompanySupervisorRole(array $managerIdsByEmail): void
    {
        User::whereIn('id', array_values($managerIdsByEmail))
            ->get()
            ->each
            ->assignRole(UserRole::COMPANY_SUPERVISOR->value);
    }

    private function managerEmail(object $oldCompany, ?object $oldManager): string
    {
        if ($oldManager && filled($oldManager->email)) {
            return Str::lower(Str::limit(trim($oldManager->email), 250, ''));
        }

        return 'manager_'.$oldCompany->c_id.'@example.com';
    }

    private function managerName(object $oldCompany, ?object $oldManager): string
    {
        $name = ($oldManager && filled($oldManager->name))
            ? $oldManager->name
            : 'مدير - '.$oldCompany->c_name;

        return Str::limit($name, 250, '');
    }

    private function managerNameEn(string $managerName, object $oldCompany, ?object $oldManager): string
    {
        if ($oldManager && filled($oldManager->name)) {
            return $managerName;
        }

        return Str::limit('Manager - '.($oldCompany->c_english_name ?? $oldCompany->c_name), 250, '');
    }

    private function managerPhone(?object $oldManager): ?string
    {
        if (! $oldManager) {
            return null;
        }

        foreach (['u_phone1', 'u_phone2', 'u_username'] as $field) {
            if (! filled($oldManager->{$field} ?? null)) {
                continue;
            }

            $phone = trim($oldManager->{$field});

            if (preg_match('/^[0-9+\-\s()]+$/', $phone)) {
                return Str::limit($phone, 250, '');
            }
        }

        return null;
    }

    private function workingHoursRows(int $branchId): array
    {
        $now = now();

        return collect([1, 2, 3, 4, 5, 6, 7])
            ->map(fn ($day) => [
                'branch_id' => $branchId,
                'day' => $day,
                'start_time' => in_array($day, [1, 7]) ? null : '08:00',
                'end_time' => in_array($day, [1, 7]) ? null : '16:00',
                'is_closed' => in_array($day, [1, 7]),
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();
    }
}
