<?php

namespace Modules\Content\database\seeders;

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
            // TODO Banner
            [
                'name' => 'Banner View List',
                'guard_name' => 'web',
                'module_name' => 'Content',
            ],
            [
                'name' => 'Banner Create',
                'guard_name' => 'web',
                'module_name' => 'Content',
            ],
            [
                'name' => 'Banner Update',
                'guard_name' => 'web',
                'module_name' => 'Content',
            ],
            [
                'name' => 'Banner Delete',
                'guard_name' => 'web',
                'module_name' => 'Content',
            ],
            [
                'name' => 'Banner View',
                'guard_name' => 'web',
                'module_name' => 'Content',
            ],
            [
                'name' => 'Banner Info',
                'guard_name' => 'web',
                'module_name' => 'Content',
            ],
            [
                'name' => 'Banner View Details',
                'guard_name' => 'web',
                'module_name' => 'Content',
            ],


            // TODO Pages
            [
                'name' => 'Page View List',
                'guard_name' => 'web',
                'module_name' => 'Content',
            ],
            [
                'name' => 'Page Create',
                'guard_name' => 'web',
                'module_name' => 'Content',
            ],
            [
                'name' => 'Page Update',
                'guard_name' => 'web',
                'module_name' => 'Content',
            ],
            [
                'name' => 'Page Delete',
                'guard_name' => 'web',
                'module_name' => 'Content',
            ],
            [
                'name' => 'Page View',
                'guard_name' => 'web',
                'module_name' => 'Content',
            ],
            [
                'name' => 'Page Info',
                'guard_name' => 'web',
                'module_name' => 'Content',
            ],
            [
                'name' => 'Page View Details',
                'guard_name' => 'web',
                'module_name' => 'Content',
            ],

            // TODO Faqs
            [
                'name' => 'Faq View List',
                'guard_name' => 'web',
                'module_name' => 'Content',
            ],
            [
                'name' => 'Faq Create',
                'guard_name' => 'web',
                'module_name' => 'Content',
            ],
            [
                'name' => 'Faq Update',
                'guard_name' => 'web',
                'module_name' => 'Content',
            ],
            [
                'name' => 'Faq Delete',
                'guard_name' => 'web',
                'module_name' => 'Content',
            ],
            [
                'name' => 'Faq View',
                'guard_name' => 'web',
                'module_name' => 'Content',
            ],
            [
                'name' => 'Faq Info',
                'guard_name' => 'web',
                'module_name' => 'Content',
            ],
            [
                'name' => 'Faq View Details',
                'guard_name' => 'web',
                'module_name' => 'Content',
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
