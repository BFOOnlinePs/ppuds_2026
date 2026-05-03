<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
    protected $signature = 'migrate:companies';

    protected $description = 'Migrate companies and transfer real managers using c_manager_id.';

    private const DEFAULT_CATEGORY_NAME_AR = 'تصنيف عام';

    private const DEFAULT_CATEGORY_NAME_EN = 'General Category';

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
            ->chunkById(500, function (Collection $oldCompanies) use ($department, $categoryIds, $defaultCategory, $bar) {
                $managers = $this->getOldManagers($oldCompanies);
                $managerIdsByEmail = $this->upsertManagers($oldCompanies, $managers);

                foreach ($oldCompanies as $oldCompany) {
                    DB::transaction(function () use ($oldCompany, $department, $categoryIds, $defaultCategory, $managers, $managerIdsByEmail) {
                        $oldManager = ! empty($oldCompany->c_manager_id)
                            ? $managers->get($oldCompany->c_manager_id)
                            : null;

                        $managerId = $managerIdsByEmail[$this->managerEmail($oldCompany, $oldManager)] ?? 1;
                        $categoryId = $categoryIds[$oldCompany->c_category_id] ?? $defaultCategory->id;

                        // 2. إنشاء الشركة
                        $company = Company::create([
                            'website' => $oldCompany->c_website ?? null,
                            'company_category_id' => $categoryId,
                            'status' => 1,
                            'created_by' => $managerId,
                            'ar' => [
                                'name' => $oldCompany->c_name,
                                'description' => Str::limit($oldCompany->c_description ?? '', 250, ''),
                            ],
                            'en' => [
                                'name' => $oldCompany->c_english_name ?? $oldCompany->c_name,
                                'description' => Str::limit($oldCompany->c_english_description ?? '', 250, ''),
                            ],
                        ]);

                        // 3. إنشاء الفرع الرئيسي
                        $branch = Branch::create([
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

                        // 4. ربط الشركة بالفرع، وربط مدير الشركة على ppu_ds_branch_department
                        $company->branches()->attach($branch->id, ['is_main' => 1]);
                        $branch->departments()->syncWithoutDetaching([
                            $department->id => ['user_id' => $managerId],
                        ]);

                        // 5. أوقات الدوام
                        BranchWorkingHour::insert($this->workingHoursRows($branch->id));
                    });

                    $bar->advance();
                }
            }, 'c_id');

        $bar->finish();

        $this->newLine();
        $this->info('Migration completed successfully!');
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
