<?php

namespace Modules\PPUDS\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as KeycloakUser;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\PPUDS\Entities\StudentProfile;

class AuthenticateViaKeycloakAction
{
    public function execute(KeycloakUser $keycloakUser): User
    {
        return DB::transaction(function () use ($keycloakUser) {
            $payload = $this->decodeTokenPayload($keycloakUser->token);
            $username = $this->cleanIdentifier($payload['preferred_username'] ?? $keycloakUser->getNickname());
            $email = $keycloakUser->getEmail();

            if (! $username && ! $email) {
                throw ValidationException::withMessages([
                    'auth' => 'لم يتم إرسال اسم المستخدم من نظام الجامعة.',
                ]);
            }

            $user = $this->findUser($username, $email);

            if (! $user) {
                throw ValidationException::withMessages([
                    'auth' => 'لا يوجد حساب مرتبط باسم المستخدم هذا في النظام.',
                ]);
            }

            $user->update([
                'name' => $keycloakUser->getName() ?: $user->name,
            ]);

            $user->assignRole(UserRole::SUPER_ADMIN->value);

            if ($user->hasRole(UserRole::STUDENT->value)) {
                StudentProfile::updateOrCreate(
                    ['user_id' => $user->id],
                    ['student_number' => $username ?: explode('@', $user->email)[0]]
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

    private function findUser(?string $username, ?string $email): ?User
    {
        if ($username) {
            $user = User::query()
                ->whereHas('studentProfile', fn ($query) => $query->where('student_number', $username))
                ->first();

            if ($user) {
                return $user;
            }
        }

        $candidateEmails = $this->candidateEmails($username, $email);

        if ($candidateEmails === []) {
            return null;
        }

        return User::whereIn('email', $candidateEmails)->first();
    }

    private function candidateEmails(?string $username, ?string $email): array
    {
        $email = $this->cleanEmail($email);

        return array_values(array_unique(array_filter([
            $email,
            $username,
            $username ? "{$username}@ppu.edu.ps" : null,
            $username ? "{$username}@ppu.edu" : null,
        ])));
    }

    private function cleanIdentifier(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function cleanEmail(mixed $value): ?string
    {
        $value = strtolower(trim((string) $value));

        return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : null;
    }

    private function decodeTokenPayload(string $token): array
    {
        $payload = explode('.', $token)[1] ?? '';
        $payload = strtr($payload, '-_', '+/');
        $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);

        return json_decode(base64_decode($payload), true) ?: [];
    }
}
