<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Core\Entities\User;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\CompanyCategory;
use Modules\PPUDS\Entities\CompanyDepartment;
use Modules\Branch\Entities\Branch;
use Modules\Branch\Entities\BranchWorkingHour;

class MigrateCompaniesData extends Command
{
    protected $signature = 'migrate:companies';
    protected $description = 'Migrate companies and transfer real managers using c_manager_id.';

    public function handle()
    {
        $this->info('Starting companies migration...');

        // 1. التأكد من وجود القسم (القسم الأول) أو إنشاؤه
        $department = CompanyDepartment::whereTranslation('name', 'القسم الأول', 'ar')->first();

        if (!$department) {
            $department = new CompanyDepartment(['created_by' => 1]);
            $department->fill([
                'ar' => ['name' => 'القسم الأول'],
                'en' => ['name' => 'First Department'],
            ]);
            $department->save();
        }

        DB::connection('old_db')->table('companies')->orderBy('c_id')->chunk(500, function ($oldCompanies) use ($department) {

            $this->withProgressBar($oldCompanies, function ($oldCompany) use ($department) {
                DB::transaction(function () use ($oldCompany, $department) {

                    // 2. جلب بيانات المدير من الداتابيز القديمة بناءً على c_manager_id
                    $oldManager = null;
                    if (!empty($oldCompany->c_manager_id)) {
                        $oldManager = DB::connection('old_db')->table('users')
                            ->where('u_id', $oldCompany->c_manager_id) // هنا التعديل السحري
                            ->first();
                    }

                    // --- معالجة بيانات المدير (الاسم، الإيميل، رقم الهاتف) ---

                    // جلب الإيميل
                    $managerEmail = ($oldManager && !empty($oldManager->email))
                        ? $oldManager->email
                        : 'manager_' . $oldCompany->c_id . '@example.com';

                    // جلب الاسم
                    $managerName = ($oldManager && !empty($oldManager->name))
                        ? $oldManager->name
                        : 'مدير - ' . $oldCompany->c_name;

                    // استخراج رقم الهاتف من حقل u_username
                    $phone = null;
                    if ($oldManager && !empty($oldManager->u_username)) {
                        if (is_numeric($oldManager->u_username)) {
                            $phone = $oldManager->u_username;
                        }
                    }

                    // إنشاء أو تحديث حساب المدير ببياناته الحقيقية
                    $manager = User::updateOrCreate(
                        ['email' => $managerEmail],
                        [
                            'name' => $managerName,
                            'name_en' => 'Manager - ' . ($oldCompany->c_english_name ?? $oldCompany->c_name),
                            'phone' => $phone,
                            'password' => Hash::make('123456789'),
                        ]
                    );

                    // 3. جلب أو إنشاء التصنيف ديناميكياً
                    $category = CompanyCategory::firstOrCreate(
                        ['id' => 1],
                        [
                            'created_by' => 1,
                            'ar' => ['name' => 'تصنيف عام'],
                            'en' => ['name' => 'General Category'],
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
                            'description' => Str::limit($oldCompany->c_description, 250, ''),
                        ],
                        'en' => [
                            'name' => $oldCompany->c_english_name ?? $oldCompany->c_name,
                            'description' => Str::limit($oldCompany->c_english_description, 250, ''),
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

                    // 6. الربط
                    $company->branches()->attach($branch->id, ['is_main' => 1]);
                    $branch->departments()->attach($department->id, ['user_id' => $manager->id]);

                    // 7. أوقات الدوام
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
