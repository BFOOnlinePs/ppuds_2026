<?php

namespace Modules\Core\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Enums\UserRole;
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
        Permission::query()->delete();

        $permissions = [
            [
                'name' => 'User View List',
                'guard_name' => 'web',
                'module_name' => 'User Management',
            ],
            [
                'name' => 'User Create',
                'guard_name' => 'web',
                'module_name' => 'User Management',
            ],
            [
                'name' => 'User Update',
                'guard_name' => 'web',
                'module_name' => 'User Management',
            ],
            [
                'name' => 'User Delete',
                'guard_name' => 'web',
                'module_name' => 'User Management',
            ],
            [
                'name' => 'User View',
                'guard_name' => 'web',
                'module_name' => 'User Management',
            ],

            // TODO Currency
            [
                'name' => 'Currency View List',
                'guard_name' => 'web',
                'module_name' => 'Currency Management',
            ],
            [
                'name' => 'Currency Create',
                'guard_name' => 'web',
                'module_name' => 'Currency Management',
            ],
            [
                'name' => 'Currency Update',
                'guard_name' => 'web',
                'module_name' => 'Currency Management',
            ],
            [
                'name' => 'Currency Delete',
                'guard_name' => 'web',
                'module_name' => 'Currency Management',
            ],
            [
                'name' => 'Currency View',
                'guard_name' => 'web',
                'module_name' => 'Currency Management',
            ],

            // TODO Roles And Permissions
            [
                'name' => 'Roles And Permissions View List',
                'guard_name' => 'web',
                'module_name' => 'Roles And Permissions',
            ],
            [
                'name' => 'Roles And Permissions Create',
                'guard_name' => 'web',
                'module_name' => 'Roles And Permissions',
            ],
            [
                'name' => 'Roles And Permissions Update',
                'guard_name' => 'web',
                'module_name' => 'Roles And Permissions',
            ],
            [
                'name' => 'Roles And Permissions Delete',
                'guard_name' => 'web',
                'module_name' => 'Roles And Permissions',
            ],
            [
                'name' => 'Roles And Permissions View',
                'guard_name' => 'web',
                'module_name' => 'Roles And Permissions',
            ],

            // TODO Settings
            [
                'name' => 'Setting View List',
                'guard_name' => 'web',
                'module_name' => 'Settings',
            ],
            [
                'name' => 'Setting Create',
                'guard_name' => 'web',
                'module_name' => 'Settings',
            ],
            [
                'name' => 'Setting Update',
                'guard_name' => 'web',
                'module_name' => 'Settings',
            ],
            [
                'name' => 'Setting Delete',
                'guard_name' => 'web',
                'module_name' => 'Settings',
            ],
            [
                'name' => 'Setting View',
                'guard_name' => 'web',
                'module_name' => 'Settings',
            ],
            [
                'name' => 'General Settings Update',
                'guard_name' => 'web',
                'module_name' => 'Settings',
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

        foreach (UserRole::cases() as $role) {
            Role::firstOrCreate(['name' => $role->value, 'guard_name' => 'web']);
            $this->command->info("Role {$role->value} created");
        }
    }
}
