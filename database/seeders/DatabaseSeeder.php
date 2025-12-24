<?php

namespace database\seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Core\database\seeders\CoreDatabaseSeeder;
use Nwidart\Modules\Facades\Module;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CoreDatabaseSeeder::class,
        ]);

        $this->command->info('🚀 Starting to seed enabled modules...');

        $enabledModules = Module::allEnabled();

        foreach ($enabledModules as $module) {
            // ✅  الحل: إضافة هذا الشرط لتخطي موديل Core
            if ($module->getName() === 'Core') {
                continue; // تخطى هذا الموديل لأنه تم تنفيذه بالفعل
            }

            $this->seedModule($module);
        }

        $this->command->info('✅ All enabled modules have been seeded!');
    }

    private function seedModule($module): void
    {
        $moduleName = $module->getName();
        $this->command->info("📦 Processing module: {$moduleName}");

        // تحديد مسار seeder الخاص بالـ module
        $seederClass = "Modules\\{$moduleName}\\database\\seeders\\{$moduleName}DatabaseSeeder";

        // التحقق من وجود الـ seeder class
        if (class_exists($seederClass)) {
            try {
                $this->command->info("   🌱 Seeding {$moduleName}...");
                $this->call($seederClass);
                $this->command->info("   ✅ {$moduleName} seeded successfully!");
            } catch (\Exception $e) {
                $this->command->error("   ❌ Error seeding {$moduleName}: " . $e->getMessage());
            }
        } else {
            // البحث عن seeders أخرى محتملة
            $alternativeSeederClass = "Modules\\{$moduleName}\\database\\seeders\\DatabaseSeeder";

            if (class_exists($alternativeSeederClass)) {
                try {
                    $this->command->info("   🌱 Seeding {$moduleName} (alternative seeder)...");
                    $this->call($alternativeSeederClass);
                    $this->command->info("   ✅ {$moduleName} seeded successfully!");
                } catch (\Exception $e) {
                    $this->command->error("   ❌ Error seeding {$moduleName}: " . $e->getMessage());
                }
            } else {
                $this->command->warn("   ⚠️  No seeder found for module: {$moduleName}");
                $this->command->warn("      Expected: {$seederClass}");
                $this->command->warn("      Or: {$alternativeSeederClass}");
            }
        }
    }
}
