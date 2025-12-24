<?php

namespace Modules\Marketing\database\seeders;

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
            // TODO Marketing
            [
                'name' => 'Marketing View',
                'guard_name' => 'web',
                'module_name' => 'Marketing View',
            ],

            // TODO Loyalty Rules
            [
                'name' => 'Loyalty Rules List',
                'guard_name' => 'web',
                'module_name' => 'Loyalty Rules',
            ],
            [
                'name' => 'Loyalty Rules Create',
                'guard_name' => 'web',
                'module_name' => 'Loyalty Rules',
            ],
            [
                'name' => 'Loyalty Rules Update',
                'guard_name' => 'web',
                'module_name' => 'Loyalty Rules',
            ],
            [
                'name' => 'Loyalty Rules Delete',
                'guard_name' => 'web',
                'module_name' => 'Loyalty Rules',
            ],
            [
                'name' => 'Loyalty Rules View',
                'guard_name' => 'web',
                'module_name' => 'Loyalty Rules',
            ],
            [
                'name' => 'Loyalty Rules Info',
                'guard_name' => 'web',
                'module_name' => 'Loyalty Rules',
            ],
            [
                'name' => 'Loyalty Rules View Details',
                'guard_name' => 'web',
                'module_name' => 'Loyalty Rules',
            ],
            [
                'name' => 'Loyalty Rules View List',
                'guard_name' => 'web',
                'module_name' => 'Loyalty Rules',
            ],


            // TODO Loyalty Tiers
            [
                'name' => 'Loyalty Tiers List',
                'guard_name' => 'web',
                'module_name' => 'Loyalty Tiers',
            ],
            [
                'name' => 'Loyalty Tiers Create',
                'guard_name' => 'web',
                'module_name' => 'Loyalty Tiers',
            ],
            [
                'name' => 'Loyalty Tiers Update',
                'guard_name' => 'web',
                'module_name' => 'Loyalty Tiers',
            ],
            [
                'name' => 'Loyalty Tiers Delete',
                'guard_name' => 'web',
                'module_name' => 'Loyalty Tiers',
            ],
            [
                'name' => 'Loyalty Tiers View',
                'guard_name' => 'web',
                'module_name' => 'Loyalty Tiers',
            ],
            [
                'name' => 'Loyalty Tiers Info',
                'guard_name' => 'web',
                'module_name' => 'Loyalty Tiers',
            ],
            [
                'name' => 'Loyalty Tiers View Details',
                'guard_name' => 'web',
                'module_name' => 'Loyalty Tiers',
            ],
            [
                'name' => 'Loyalty Tiers View List',
                'guard_name' => 'web',
                'module_name' => 'Loyalty Tiers',
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
