<?php

namespace Modules\Branch\database\seeders;

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
            [
                'name' => 'Branch View List',
                'guard_name' => 'web',
                'module_name' => 'Branch Management',
            ],
            [
                'name' => 'Branch Create',
                'guard_name' => 'web',
                'module_name' => 'Branch Management',
            ],
            [
                'name' => 'Branch Update',
                'guard_name' => 'web',
                'module_name' => 'Branch Management',
            ],
            [
                'name' => 'Branch Delete',
                'guard_name' => 'web',
                'module_name' => 'Branch Management',
            ],
            [
                'name' => 'Branch View',
                'guard_name' => 'web',
                'module_name' => 'Branch Management',
            ],
            [
                'name' => 'Branch Info',
                'guard_name' => 'web',
                'module_name' => 'Branch Management',
            ],
            [
                'name' => 'Branch View Details',
                'guard_name' => 'web',
                'module_name' => 'Branch Management',
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
