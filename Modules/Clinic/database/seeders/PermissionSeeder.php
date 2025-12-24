<?php

namespace Modules\Clinic\database\seeders;

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
                'name' => 'Room View List',
                'guard_name' => 'web',
                'module_name' => 'Room Management',
            ],
            [
                'name' => 'Room Create',
                'guard_name' => 'web',
                'module_name' => 'Room Management',
            ],
            [
                'name' => 'Room Update',
                'guard_name' => 'web',
                'module_name' => 'Room Management',
            ],
            [
                'name' => 'Room Delete',
                'guard_name' => 'web',
                'module_name' => 'Room Management',
            ],
            [
                'name' => 'Room View',
                'guard_name' => 'web',
                'module_name' => 'Room Management',
            ],
            [
                'name' => 'Room Info',
                'guard_name' => 'web',
                'module_name' => 'Room Management',
            ],
            [
                'name' => 'Room View Details',
                'guard_name' => 'web',
                'module_name' => 'Room Management',
            ],

            // TODO Disease
            [
                'name' => 'Disease View List',
                'guard_name' => 'web',
                'module_name' => 'Disease Management',
            ],
            [
                'name' => 'Disease Create',
                'guard_name' => 'web',
                'module_name' => 'Disease Management',
            ],
            [
                'name' => 'Disease Update',
                'guard_name' => 'web',
                'module_name' => 'Disease Management',
            ],
            [
                'name' => 'Disease Delete',
                'guard_name' => 'web',
                'module_name' => 'Disease Management',
            ],
            [
                'name' => 'Disease View',
                'guard_name' => 'web',
                'module_name' => 'Disease Management',
            ],
            [
                'name' => 'Disease Info',
                'guard_name' => 'web',
                'module_name' => 'Disease Management',
            ],
            [
                'name' => 'Disease View Details',
                'guard_name' => 'web',
                'module_name' => 'Disease Management',
            ],

            // TODO Program Categories
            [
                'name' => 'Program Category View List',
                'guard_name' => 'web',
                'module_name' => 'Program Categories Management',
            ],
            [
                'name' => 'Program Category Create',
                'guard_name' => 'web',
                'module_name' => 'Program Categories Management',
            ],
            [
                'name' => 'Program Category Update',
                'guard_name' => 'web',
                'module_name' => 'Program Categories Management',
            ],
            [
                'name' => 'Program Category Delete',
                'guard_name' => 'web',
                'module_name' => 'Program Categories Management',
            ],
            [
                'name' => 'Program Category View',
                'guard_name' => 'web',
                'module_name' => 'Program Categories Management',
            ],
            [
                'name' => 'Program Category Info',
                'guard_name' => 'web',
                'module_name' => 'Program Categories Management',
            ],
            [
                'name' => 'Program Category View Details',
                'guard_name' => 'web',
                'module_name' => 'Program Categories Management',
            ],

            // TODO Type Of Meal
            [
                'name' => 'Program Type Of Meal View List',
                'guard_name' => 'web',
                'module_name' => 'Type Of Meal Management',
            ],
            [
                'name' => 'Program Type Of Meal Create',
                'guard_name' => 'web',
                'module_name' => 'Type Of Meal Management',
            ],
            [
                'name' => 'Program Type Of Meal Update',
                'guard_name' => 'web',
                'module_name' => 'Type Of Meal Management',
            ],
            [
                'name' => 'Program Type Of Meal Delete',
                'guard_name' => 'web',
                'module_name' => 'Type Of Meal Management',
            ],
            [
                'name' => 'Program Type Of Meal View',
                'guard_name' => 'web',
                'module_name' => 'Type Of Meal Management',
            ],
            [
                'name' => 'Program Type Of Meal Info',
                'guard_name' => 'web',
                'module_name' => 'Type Of Meal Management',
            ],
            [
                'name' => 'Program Type Of Meal View Details',
                'guard_name' => 'web',
                'module_name' => 'Type Of Meal Management',
            ],

            // TODO Instructions
            [
                'name' => 'Program Instruction View List',
                'guard_name' => 'web',
                'module_name' => 'Instructions Management',
            ],
            [
                'name' => 'Program Instruction Create',
                'guard_name' => 'web',
                'module_name' => 'Instructions Management',
            ],
            [
                'name' => 'Program Instruction Update',
                'guard_name' => 'web',
                'module_name' => 'Instructions Management',
            ],
            [
                'name' => 'Program Instruction Delete',
                'guard_name' => 'web',
                'module_name' => 'Instructions Management',
            ],
            [
                'name' => 'Program Instruction View',
                'guard_name' => 'web',
                'module_name' => 'Instructions Management',
            ],
            [
                'name' => 'Program Instruction Info',
                'guard_name' => 'web',
                'module_name' => 'Instructions Management',
            ],
            [
                'name' => 'Program Instruction View Details',
                'guard_name' => 'web',
                'module_name' => 'Instructions Management',
            ],

            // TODO Clinic Setting
            [
                'name' => 'Clinic Setting View',
                'guard_name' => 'web',
                'module_name' => 'Clinic Setting Management',
            ],
            [
                'name' => 'Clinic Setting View List',
                'guard_name' => 'web',
                'module_name' => 'Clinic Setting Management',
            ],
            [
                'name' => 'Clinic Setting Create',
                'guard_name' => 'web',
                'module_name' => 'Clinic Setting Management',
            ],
            [
                'name' => 'Clinic Setting Update',
                'guard_name' => 'web',
                'module_name' => 'Clinic Setting Management',
            ],
            [
                'name' => 'Clinic Setting Delete',
                'guard_name' => 'web',
                'module_name' => 'Clinic Setting Management',
            ],
            [
                'name' => 'Clinic Setting View',
                'guard_name' => 'web',
                'module_name' => 'Clinic Setting Management',
            ],
            [
                'name' => 'Clinic Setting Info',
                'guard_name' => 'web',
                'module_name' => 'Clinic Setting Management',
            ],
            [
                'name' => 'Clinic Setting View Details',
                'guard_name' => 'web',
                'module_name' => 'Clinic Setting Management',
            ],

            // TODO Food Category
            [
                'name' => 'Food View',
                'guard_name' => 'web',
                'module_name' => 'Food Management',
            ],
            [
                'name' => 'Food Category View',
                'guard_name' => 'web',
                'module_name' => 'Food Category Management',
            ],
            [
                'name' => 'Food Category View List',
                'guard_name' => 'web',
                'module_name' => 'Food Category Management',
            ],
            [
                'name' => 'Food Category Create',
                'guard_name' => 'web',
                'module_name' => 'Food Category Management',
            ],
            [
                'name' => 'Food Category Update',
                'guard_name' => 'web',
                'module_name' => 'Food Category Management',
            ],
            [
                'name' => 'Food Category Delete',
                'guard_name' => 'web',
                'module_name' => 'Food Category Management',
            ],
            [
                'name' => 'Food Category View',
                'guard_name' => 'web',
                'module_name' => 'Food Category Management',
            ],
            [
                'name' => 'Food Category Info',
                'guard_name' => 'web',
                'module_name' => 'Food Category Management',
            ],
            [
                'name' => 'Food Category View Details',
                'guard_name' => 'web',
                'module_name' => 'Food Category Management',
            ],

            // TODO Food Item
            [
                'name' => 'Food Item View List',
                'guard_name' => 'web',
                'module_name' => 'Food Item Management',
            ],
            [
                'name' => 'Food Item Create',
                'guard_name' => 'web',
                'module_name' => 'Food Item Management',
            ],
            [
                'name' => 'Food Item Update',
                'guard_name' => 'web',
                'module_name' => 'Food Item Management',
            ],
            [
                'name' => 'Food Item Delete',
                'guard_name' => 'web',
                'module_name' => 'Food Item Management',
            ],
            [
                'name' => 'Food Item View',
                'guard_name' => 'web',
                'module_name' => 'Food Item Management',
            ],
            [
                'name' => 'Food Item Info',
                'guard_name' => 'web',
                'module_name' => 'Food Item Management',
            ],
            [
                'name' => 'Food Item View Details',
                'guard_name' => 'web',
                'module_name' => 'Food Item Management',
            ],

            // TODO Program
            [
                'name' => 'Program View List',
                'guard_name' => 'web',
                'module_name' => 'Program Management',
            ],
            [
                'name' => 'Program Create',
                'guard_name' => 'web',
                'module_name' => 'Program Management',
            ],
            [
                'name' => 'Program Update',
                'guard_name' => 'web',
                'module_name' => 'Program Management',
            ],
            [
                'name' => 'Program Delete',
                'guard_name' => 'web',
                'module_name' => 'Program Management',
            ],
            [
                'name' => 'Program View',
                'guard_name' => 'web',
                'module_name' => 'Program Management',
            ],
            [
                'name' => 'Program Info',
                'guard_name' => 'web',
                'module_name' => 'Program Management',
            ],
            [
                'name' => 'Program View Details',
                'guard_name' => 'web',
                'module_name' => 'Program Management',
            ],

            // TODO Program Details
            [
                'name' => 'Program Details View List',
                'guard_name' => 'web',
                'module_name' => 'Program Details Management',
            ],

            // TODO Survey
            [
                'name' => 'Survey View List',
                'guard_name' => 'web',
                'module_name' => 'Survey Management',
            ],
            [
                'name' => 'Survey Create',
                'guard_name' => 'web',
                'module_name' => 'Survey Management',
            ],
            [
                'name' => 'Survey Update',
                'guard_name' => 'web',
                'module_name' => 'Survey Management',
            ],
            [
                'name' => 'Survey Delete',
                'guard_name' => 'web',
                'module_name' => 'Survey Management',
            ],
            [
                'name' => 'Survey View',
                'guard_name' => 'web',
                'module_name' => 'Survey Management',
            ],
            [
                'name' => 'Survey Info',
                'guard_name' => 'web',
                'module_name' => 'Survey Management',
            ],
            [
                'name' => 'Survey View Details',
                'guard_name' => 'web',
                'module_name' => 'Survey Management',
            ],

            // TODO Question
            [
                'name' => 'Clinic Survey Question View List',
                'guard_name' => 'web',
                'module_name' => 'Clinic Survey Management',
            ],
            [
                'name' => 'Clinic Survey Question Create',
                'guard_name' => 'web',
                'module_name' => 'Clinic Management',
            ],
            [
                'name' => 'Clinic Survey Question Update',
                'guard_name' => 'web',
                'module_name' => 'Clinic Survey Management',
            ],
            [
                'name' => 'Clinic Survey Question Delete',
                'guard_name' => 'web',
                'module_name' => 'Clinic Survey Management',
            ],
            [
                'name' => 'Clinic Survey Question View',
                'guard_name' => 'web',
                'module_name' => 'Clinic Survey Management',
            ],
            [
                'name' => 'Clinic Survey Question Info',
                'guard_name' => 'web',
                'module_name' => 'Clinic Survey Management',
            ],
            [
                'name' => 'Clinic Survey Question View Details',
                'guard_name' => 'web',
                'module_name' => 'Clinic Survey Management',
            ],

            // TODO Customer Program
            [
                'name' => 'Customer Program View List',
                'guard_name' => 'web',
                'module_name' => 'Customer Program Management',
            ],
            [
                'name' => 'Customer Program Create',
                'guard_name' => 'web',
                'module_name' => 'Customer Program Management',
            ],
            [
                'name' => 'Customer Program Update',
                'guard_name' => 'web',
                'module_name' => 'Customer Program Management',
            ],
            [
                'name' => 'Customer Program Delete',
                'guard_name' => 'web',
                'module_name' => 'Customer Program Management',
            ],
            [
                'name' => 'Customer Program View',
                'guard_name' => 'web',
                'module_name' => 'Customer Program Management',
            ],
            [
                'name' => 'Customer Program Info',
                'guard_name' => 'web',
                'module_name' => 'Customer Program Management',
            ],
            [
                'name' => 'Customer Program View Details',
                'guard_name' => 'web',
                'module_name' => 'Customer Program Management',
            ],
            [
                'name' => 'Customer Program Print',
                'guard_name' => 'web',
                'module_name' => 'Customer Program Management',
            ],

            // TODO Appointment
            [
                'name' => 'Appointment View List',
                'guard_name' => 'web',
                'module_name' => 'Appointment Management',
            ],
            [
                'name' => 'Appointment Create',
                'guard_name' => 'web',
                'module_name' => 'Appointment Management',
            ],
            [
                'name' => 'Appointment Update',
                'guard_name' => 'web',
                'module_name' => 'Appointment Management',
            ],
            [
                'name' => 'Appointment Delete',
                'guard_name' => 'web',
                'module_name' => 'Appointment Management',
            ],
            [
                'name' => 'Appointment View',
                'guard_name' => 'web',
                'module_name' => 'Appointment Management',
            ],
            [
                'name' => 'Appointment Info',
                'guard_name' => 'web',
                'module_name' => 'Appointment Management',
            ],
            [
                'name' => 'Appointment View Details',
                'guard_name' => 'web',
                'module_name' => 'Appointment Management',
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
