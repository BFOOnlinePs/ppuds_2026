<?php

namespace Modules\PPUDS\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as KeycloakUser;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\PPUDS\Entities\StudentProfile;

class AuthenticateViaKeycloakAction
{
    public function execute(KeycloakUser $keycloakUser): User
    {
        // dd($keycloakUser);
        return DB::transaction(function () use ($keycloakUser) {
            // 1. فك تشفير التوكن واستخراج الأدوار (Roles)
            $payload = json_decode(base64_decode(explode('.', $keycloakUser->token)[1]), true);
            $roles = $payload['realm_access']['roles'] ?? [];

            // 2. تحديث أو إنشاء المستخدم (Core User)
            $user = User::updateOrCreate(
                ['email' => $keycloakUser->getEmail()],
                [
                    'name' => $keycloakUser->getName(),
                    'password' => bcrypt(Str::random(24)),
                ]
            );

            // 3. مزامنة الأدوار (Spatie Roles) - نفترض وجود دور Student و Supervisor
            // $user->syncRoles(array_intersect($roles, [UserRole::STUDENT->value, UserRole::COMPANY_SUPERVISOR->value, UserRole::SUPER_ADMIN->value]));

            // $user->assignRole();

            // dd(array_intersect($roles, [UserRole::STUDENT->value, UserRole::COMPANY_SUPERVISOR->value, UserRole::SUPER_ADMIN->value]));

            // $keycloakRoles = array_intersect($roles, [
            //     UserRole::STUDENT->value,
            //     UserRole::COMPANY_SUPERVISOR->value,
            // ]);

            // // 2. إضافة Super Admin بشكل إجباري ودائم للمصفوفة
            // $keycloakRoles[] = UserRole::SUPER_ADMIN->value;

            // 3. إسناد جميع الأدوار للمستخدم
            $user->assignRole(UserRole::SUPER_ADMIN->value);

            // 4. تحديث ملف الطالب الشخصي (PPUDS Profile)
            if ($user->hasRole(UserRole::STUDENT->value)) {
                StudentProfile::updateOrCreate(
                    ['user_id' => $user->id],
                    ['student_number' => explode('@', $user->email)[0]]
                );
            }

            $user->generateAvatar();

            Auth::login($user);

            session(['keycloak_access_token' => $keycloakUser->token]);

            return $user;
        });
    }
}
