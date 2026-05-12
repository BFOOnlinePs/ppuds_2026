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

        $dstesting = User::updateOrCreate(
            ['email' => 'dstesting@ppu.edu'],
            [
                'name' => 'DSTesting',
                'password' => Hash::make('123456789'),
            ]
        );

        $dstesting->assignRole('Super Admin');

        $dstesting->generateAvatar();

        $roles = [
            'Student',                         // طالب
            'Head of Department',              // رئيس قسم
            'Administrative Assistant',        // مساعد اداري
            'M&E Officer',                     // مسؤول متابعة وتقييم (Monitoring and Evaluation)
            'Corporate Relations Officer',      // مسؤول التواصل مع الشركات
            'Practical Training Supervisor',   // مشرف التدريب العملي
            'Company Supervisor',              // مسؤول متابعة في الشركة
        ];

        foreach ($roles as $role){
            Role::findOrCreate($role);
        }
    }
}
