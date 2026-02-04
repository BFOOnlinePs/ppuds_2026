<?php

namespace Modules\PPUDS\database\seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        // Role::query()->delete();
        // Permission::query()->delete();

        $permissions = [
            // TODO Students
            [
                'name' => 'Student View List',
                'guard_name' => 'web',
                'module_name' => 'Student',
            ],
            [
                'name' => 'Student Create',
                'guard_name' => 'web',
                'module_name' => 'Student',
            ],
            [
                'name' => 'Student Update',
                'guard_name' => 'web',
                'module_name' => 'Student',
            ],
            [
                'name' => 'Student Delete',
                'guard_name' => 'web',
                'module_name' => 'Student',
            ],
            [
                'name' => 'Student View',
                'guard_name' => 'web',
                'module_name' => 'Student',
            ],
            [
                'name' => 'Student Info',
                'guard_name' => 'web',
                'module_name' => 'Student',
            ],


            // TODO Company Category
            [
                'name' => 'Company Category View List',
                'guard_name' => 'web',
                'module_name' => 'Company Category',
            ],
            [
                'name' => 'Company Category Create',
                'guard_name' => 'web',
                'module_name' => 'Company Category',
            ],
            [
                'name' => 'Company Category Update',
                'guard_name' => 'web',
                'module_name' => 'Company Category',
            ],
            [
                'name' => 'Company Category Delete',
                'guard_name' => 'web',
                'module_name' => 'Company Category',
            ],
            [
                'name' => 'Company Category View',
                'guard_name' => 'web',
                'module_name' => 'Company Category',
            ],
            [
                'name' => 'Company Category Info',
                'guard_name' => 'web',
                'module_name' => 'Company Category',
            ],


            // TODO Companies
            [
                'name' => 'Company View List',
                'guard_name' => 'web',
                'module_name' => 'Company',
            ],
            [
                'name' => 'Company Create',
                'guard_name' => 'web',
                'module_name' => 'Company',
            ],
            [
                'name' => 'Company Update',
                'guard_name' => 'web',
                'module_name' => 'Company',
            ],
            [
                'name' => 'Company Delete',
                'guard_name' => 'web',
                'module_name' => 'Company',
            ],
            [
                'name' => 'Company View',
                'guard_name' => 'web',
                'module_name' => 'Company',
            ],
            [
                'name' => 'Company Info',
                'guard_name' => 'web',
                'module_name' => 'Company',
            ],


            // TODO Companies Department
            [
                'name' => 'Company Department View List',
                'guard_name' => 'web',
                'module_name' => 'Company Department',
            ],
            [
                'name' => 'Company Department Create',
                'guard_name' => 'web',
                'module_name' => 'Company Department',
            ],
            [
                'name' => 'Company Department Update',
                'guard_name' => 'web',
                'module_name' => 'Company Department',
            ],
            [
                'name' => 'Company Department Delete',
                'guard_name' => 'web',
                'module_name' => 'Company Department',
            ],
            [
                'name' => 'Company Department View',
                'guard_name' => 'web',
                'module_name' => 'Company Department',
            ],
            [
                'name' => 'Company Department Info',
                'guard_name' => 'web',
                'module_name' => 'Company Department',
            ],


            // TODO Major
            [
                'name' => 'Major View List',
                'guard_name' => 'web',
                'module_name' => 'Major',
            ],
            [
                'name' => 'Major Create',
                'guard_name' => 'web',
                'module_name' => 'Major',
            ],
            [
                'name' => 'Major Update',
                'guard_name' => 'web',
                'module_name' => 'Major',
            ],
            [
                'name' => 'Major Delete',
                'guard_name' => 'web',
                'module_name' => 'Company',
            ],
            [
                'name' => 'Major View',
                'guard_name' => 'web',
                'module_name' => 'Major',
            ],
            [
                'name' => 'Major Info',
                'guard_name' => 'web',
                'module_name' => 'Major',
            ],


            // TODO Course
            [
                'name' => 'Course View List',
                'guard_name' => 'web',
                'module_name' => 'Course',
            ],
            [
                'name' => 'Course Create',
                'guard_name' => 'web',
                'module_name' => 'Course',
            ],
            [
                'name' => 'Course Update',
                'guard_name' => 'web',
                'module_name' => 'Course',
            ],
            [
                'name' => 'Course Delete',
                'guard_name' => 'web',
                'module_name' => 'Course',
            ],
            [
                'name' => 'Course View',
                'guard_name' => 'web',
                'module_name' => 'Course',
            ],
            [
                'name' => 'Course Info',
                'guard_name' => 'web',
                'module_name' => 'Course',
            ],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(
                ['name' => $perm['name'], 'guard_name' => 'web'],
                ['module_name' => $perm['module_name']]
            );
            $this->command->info("Permission created: {$perm['name']}");

            $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
            $role->givePermissionTo($perm['name']);
            $this->command->info("Permission {$perm['name']} assigned to role Super Admin");
        }
    }
}
