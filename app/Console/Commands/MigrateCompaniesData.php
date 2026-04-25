<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Entities\User;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\CompanyCategory;
use Modules\PPUDS\Entities\CompanyDepartment;
use Modules\Branch\Entities\Branch;
use Modules\Branch\Entities\BranchWorkingHour;

class MigrateCompaniesData extends Command
{
    protected $signature = 'migrate:companies';

    protected $description = 'Migrate companies from old database to the new PPUDS system with their managers, branches, and schedules.';

    public function handle()
    {
        $this->info('Starting companies migration...');

        DB::connection('old_db')->table('companies')->orderBy('c_id')->chunk(500, function ($oldCompanies) {

            $this->withProgressBar($oldCompanies, function ($oldCompany) {
                DB::transaction(function () use ($oldCompany) {

                    // 1. إنشاء حساب المدير
                    $managerEmail = 'manager_' . $oldCompany->c_id . '@example.com';
                    $manager = User::firstOrCreate(
                        ['email' => $managerEmail],
                        [
                            'name' => 'مدير - ' . $oldCompany->c_name,
                            'name_en' => 'Manager - ' . ($oldCompany->c_english_name ?? $oldCompany->c_name),
                            'password' => Hash::make('password123'),
                        ]
                    );

                    // 2. إنشاء تصنيف الشركة
                    $category = CompanyCategory::firstOrCreate(
                        ['id' => 1],
                        [
                            'created_by' => 1,
                            'ar' => ['name' => 'تصنيف عام'],
                            'en' => ['name' => 'General Category'],
                        ]
                    );

                    // 3. إنشاء القسم الأول
                    $department = CompanyDepartment::firstOrCreate(
                        ['id' => 1],
                        [
                            'created_by' => 1,
                            'ar' => ['name' => 'القسم الأول'],
                            'en' => ['name' => 'First Department'],
                        ]
                    );

                    // 4. إنشاء الشركة
                    $company = Company::create([
                        'website' => $oldCompany->c_website ?? null,
                        'company_category_id' => $category->id,
                        'status' => 1,
                        'created_by' => $manager->id,
                        'ar' => [
                            'name' => $oldCompany->c_name,
                            'description' => $oldCompany->c_description,
                        ],
                        'en' => [
                            'name' => $oldCompany->c_english_name ?? $oldCompany->c_name,
                            'description' => $oldCompany->c_english_description,
                        ],
                    ]);

                    // 5. إنشاء الفرع الرئيسي
                    $branch = Branch::create([
                        'status' => 1,
                        'created_by' => $manager->id,
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

                    // 6. إسناد الفرع للشركة
                    $company->branches()->attach($branch->id, ['is_main' => 1]);

                    // 7. إسناد القسم للفرع
                    $branch->departments()->attach($department->id, ['user_id' => $manager->id]);

                    // 8. إضافة أوقات الدوام للفرع
                    // سنقوم بتمرير الأرقام مباشرة (2 = الأحد، وصولاً إلى 6 = الخميس)
                    // لارافيل سيقوم بتحويلها تلقائياً إلى Enum داخل الموديل بسبب الـ Cast
                    $workingDays = [2, 3, 4, 5, 6];

                    foreach ($workingDays as $day) {
                        BranchWorkingHour::create([
                            'branch_id' => $branch->id,
                            'day' => $day,
                            'start_time' => '08:00',
                            'end_time' => '16:00',
                            'is_closed' => false,
                        ]);
                    }

                });
            });

        });

        $this->newLine();
        $this->info('Migration completed successfully!');
    }
}
