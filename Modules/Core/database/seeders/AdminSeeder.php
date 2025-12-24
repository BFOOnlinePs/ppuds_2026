<?php

namespace Modules\Core\database\seeders;

use Modules\Core\Entities\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeders.
     */
    public function run(): void
    {
        Role::findOrCreate('Super Admin');

        $user = User::updateOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Mohamad Maraqa',
                'password' => Hash::make('123456789'),
            ]
        );

        $user->assignRole('Super Admin');

        $user->generateAvatar();

        // Kitchen For Pos

        $permission = Permission::create(['name' => 'access_kitchen_display']);

        $kitchen = Role::findOrCreate('Kitchen');

        $kitchen->givePermissionTo($permission);
    }
}
