<?php

namespace Modules\PPUDS\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as KeycloakUser;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\PPUDS\Entities\StudentProfile;
use Spatie\Permission\Models\Role;

class AuthenticateViaKeycloakAction
{
    public function execute(KeycloakUser $keycloakUser): User
    {
        return DB::transaction(function () use ($keycloakUser) {
            $email = $keycloakUser->getEmail();
            dd($email);
            if (! $email) {
                throw ValidationException::withMessages([
                    'auth' => 'لم يتم إرسال البريد الإلكتروني من نظام الجامعة.',
                ]);
            }

            $name = $keycloakUser->getName() ?: explode('@', $email)[0];

            $user = User::firstOrCreate([
                'email' => $email,
            ], [
                'name' => $name,
                'password' => Hash::make(Str::random(32)),
            ]);

            if ($user->wasRecentlyCreated === false && $user->name !== $name) {
                $user->update(['name' => $name]);
            }

            $adminRole = Role::findOrCreate(UserRole::SUPER_ADMIN->value);

            $user->assignRole($adminRole);

            if ($user->hasRole(UserRole::STUDENT->value)) {
                StudentProfile::updateOrCreate(
                    ['user_id' => $user->id],
                    ['student_number' => explode('@', $user->email)[0]]
                );
            }

            $user->generateAvatar();

            Auth::login($user);

            session([
                'keycloak_access_token' => $keycloakUser->token,
                'keycloak_refresh_token' => $keycloakUser->refreshToken,
            ]);

            return $user;
        });
    }
}
