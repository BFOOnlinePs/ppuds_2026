<?php

namespace Modules\Subscription\database\seeders;

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
                'name' => 'Subscription View List',
                'guard_name' => 'web',
                'module_name' => 'subscription Management',
            ],
            [
                'name' => 'Subscription Create',
                'guard_name' => 'web',
                'module_name' => 'subscription Management',
            ],
            [
                'name' => 'Subscription Update',
                'guard_name' => 'web',
                'module_name' => 'Subscription Management',
            ],
            [
                'name' => 'Subscription Delete',
                'guard_name' => 'web',
                'module_name' => 'Subscription Management',
            ],
            [
                'name' => 'Subscription View',
                'guard_name' => 'web',
                'module_name' => 'Subscription Management',
            ],
            [
                'name' => 'Subscription Info',
                'guard_name' => 'web',
                'module_name' => 'Subscription Management',
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
