<?php

namespace Modules\PPUDS\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as KeycloakUser;
use Modules\Core\Entities\User;
use Modules\PPUDS\Entities\StudentProfile;

class AuthenticateViaKeycloakAction
{
    public function execute(KeycloakUser $keycloakUser): User
    {
        return DB::transaction(function () use ($keycloakUser) {
            // 1. فك تشفير التوكن واستخراج الأدوار (Roles)
            $payload = json_decode(base64_decode(explode('.', $keycloakUser->token)[1]), true);
            $roles = $payload['realm_access']['roles'] ?? [];

            // 2. تحديث أو إنشاء المستخدم (Core User)
            $user = User::updateOrCreate(
                ['email' => $keycloakUser->getEmail()],
                [
                    'name'     => $keycloakUser->getName(),
                    'password' => bcrypt(Str::random(24)),
                ]
            );

            // 3. مزامنة الأدوار (Spatie Roles) - نفترض وجود دور Student و Supervisor
            $user->syncRoles(array_intersect($roles, ['Student', 'Supervisor', 'Admin']));

            // 4. تحديث ملف الطالب الشخصي (PPUDS Profile)
            if ($user->hasRole('Student')) {
                StudentProfile::updateOrCreate(
                    ['user_id' => $user->id],
                    ['student_number' => explode('@', $user->email)[0]]
                );
            }

            Auth::login($user);
            return $user;
        });
    }
}
