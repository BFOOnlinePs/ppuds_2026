<?php

namespace Modules\Delivery\database\seeders;

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
            // TODO Delivery
            [
                'name' => 'Delivery View',
                'guard_name' => 'web',
                'module_name' => 'Delivery View',
            ],

            // TODO Delivery Pricing
            [
                'name' => 'Delivery Pricing List',
                'guard_name' => 'web',
                'module_name' => 'Delivery Pricing',
            ],
            [
                'name' => 'Delivery Pricing Create',
                'guard_name' => 'web',
                'module_name' => 'Delivery Pricing',
            ],
            [
                'name' => 'Delivery Pricing Update',
                'guard_name' => 'web',
                'module_name' => 'Delivery Pricing',
            ],
            [
                'name' => 'Delivery Pricing Delete',
                'guard_name' => 'web',
                'module_name' => 'Delivery Pricing',
            ],
            [
                'name' => 'Delivery Pricing View',
                'guard_name' => 'web',
                'module_name' => 'Delivery Pricing',
            ],
            [
                'name' => 'Delivery Pricing Info',
                'guard_name' => 'web',
                'module_name' => 'Delivery Pricing',
            ],
            [
                'name' => 'Delivery Pricing View Details',
                'guard_name' => 'web',
                'module_name' => 'Delivery Pricing',
            ],
            [
                'name' => 'Delivery Pricing View List',
                'guard_name' => 'web',
                'module_name' => 'Delivery Pricing',
            ],

            // TODO Delivery Zones
            [
                'name' => 'Delivery Zone List',
                'guard_name' => 'web',
                'module_name' => 'Delivery Zone',
            ],
            [
                'name' => 'Delivery Zone Create',
                'guard_name' => 'web',
                'module_name' => 'Delivery Zone',
            ],
            [
                'name' => 'Delivery Zone Update',
                'guard_name' => 'web',
                'module_name' => 'Delivery Zone',
            ],
            [
                'name' => 'Delivery Zone Delete',
                'guard_name' => 'web',
                'module_name' => 'Delivery Zone',
            ],
            [
                'name' => 'Delivery Zone View',
                'guard_name' => 'web',
                'module_name' => 'Delivery Zone',
            ],
            [
                'name' => 'Delivery Zone Info',
                'guard_name' => 'web',
                'module_name' => 'Delivery Zone',
            ],
            [
                'name' => 'Delivery Zone View Details',
                'guard_name' => 'web',
                'module_name' => 'Delivery Zone',
            ],
            [
                'name' => 'Delivery Zone View List',
                'guard_name' => 'web',
                'module_name' => 'Delivery Zone',
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
