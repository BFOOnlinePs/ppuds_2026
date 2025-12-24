<?php

namespace Modules\GeoLocation\database\seeders;

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
                'name' => 'GeoLocation View',
                'guard_name' => 'web',
                'module_name' => 'Country Management',
            ],
            [
                'name' => 'Country View List',
                'guard_name' => 'web',
                'module_name' => 'Country Management',
            ],
            [
                'name' => 'Country Create',
                'guard_name' => 'web',
                'module_name' => 'Country Management',
            ],
            [
                'name' => 'Country Update',
                'guard_name' => 'web',
                'module_name' => 'Country Management',
            ],
            [
                'name' => 'Country Delete',
                'guard_name' => 'web',
                'module_name' => 'Country Management',
            ],
            [
                'name' => 'Country View',
                'guard_name' => 'web',
                'module_name' => 'Country Management',
            ],
            [
                'name' => 'Country Info',
                'guard_name' => 'web',
                'module_name' => 'Country Management',
            ],

            //TODO Governorate
            [
                'name' => 'Governorate View List',
                'guard_name' => 'web',
                'module_name' => 'Governorate Management',
            ],
            [
                'name' => 'Governorate Create',
                'guard_name' => 'web',
                'module_name' => 'Governorate Management',
            ],
            [
                'name' => 'Governorate Update',
                'guard_name' => 'web',
                'module_name' => 'Governorate Management',
            ],
            [
                'name' => 'Governorate Delete',
                'guard_name' => 'web',
                'module_name' => 'Governorate Management',
            ],
            [
                'name' => 'Governorate View',
                'guard_name' => 'web',
                'module_name' => 'Governorate Management',
            ],
            [
                'name' => 'Governorate Info',
                'guard_name' => 'web',
                'module_name' => 'Governorate Management',
            ],

            // TODO City
            [
                'name' => 'City View List',
                'guard_name' => 'web',
                'module_name' => 'City Management',
            ],
            [
                'name' => 'City Create',
                'guard_name' => 'web',
                'module_name' => 'City Management',
            ],
            [
                'name' => 'City Update',
                'guard_name' => 'web',
                'module_name' => 'City Management',
            ],
            [
                'name' => 'City Delete',
                'guard_name' => 'web',
                'module_name' => 'City Management',
            ],
            [
                'name' => 'City View',
                'guard_name' => 'web',
                'module_name' => 'City Management',
            ],
            [
                'name' => 'City Info',
                'guard_name' => 'web',
                'module_name' => 'City Management',
            ],

            // TODO District
            [
                'name' => 'District View List',
                'guard_name' => 'web',
                'module_name' => 'District Management',
            ],
            [
                'name' => 'District Create',
                'guard_name' => 'web',
                'module_name' => 'District Management',
            ],
            [
                'name' => 'District Update',
                'guard_name' => 'web',
                'module_name' => 'District Management',
            ],
            [
                'name' => 'District Delete',
                'guard_name' => 'web',
                'module_name' => 'District Management',
            ],
            [
                'name' => 'District View',
                'guard_name' => 'web',
                'module_name' => 'District Management',
            ],
            [
                'name' => 'District Info',
                'guard_name' => 'web',
                'module_name' => 'District Management',
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
