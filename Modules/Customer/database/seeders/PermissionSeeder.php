<?php

namespace Modules\Customer\database\seeders;

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
                'name' => 'Customer View List',
                'guard_name' => 'web',
                'module_name' => 'Customer Management',
            ],
            [
                'name' => 'Customer Create',
                'guard_name' => 'web',
                'module_name' => 'Customer Management',
            ],
            [
                'name' => 'Customer Update',
                'guard_name' => 'web',
                'module_name' => 'Customer Management',
            ],
            [
                'name' => 'Customer Delete',
                'guard_name' => 'web',
                'module_name' => 'Customer Management',
            ],
            [
                'name' => 'Customer View',
                'guard_name' => 'web',
                'module_name' => 'Customer Management',
            ],
            [
                'name' => 'Customer Info',
                'guard_name' => 'web',
                'module_name' => 'Customer Management',
            ],
            [
                'name' => 'Customer View Details',
                'guard_name' => 'web',
                'module_name' => 'Customer Management',
            ],
            [
                'name' => 'Customer Deposit Create',
                'guard_name' => 'web',
                'module_name' => 'Customer Management',
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
