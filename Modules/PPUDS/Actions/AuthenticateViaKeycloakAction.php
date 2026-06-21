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
    private const TEMPORARILY_BLOCKED_ROLE = 'role-19';

    private const TEMPORARILY_BLOCKED_MESSAGE = 'لا يمكنك الدخول لحسابك مؤقتا يرجى مراجعة مركز الحاسوب رمز الخطا role-19';

    public function execute(KeycloakUser $keycloakUser): User
    {
        $payload = $this->decodeTokenPayload($keycloakUser->token);

        $this->ensureUserIsAllowedByKeycloakRoles($payload);

        return DB::transaction(function () use ($keycloakUser, $payload) {
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

            if (! $user->name && $keycloakUser->getName()) {
                $user->update(['name' => $keycloakUser->getName()]);
            }

            $this->syncStudentIdentity($user, $username);

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

            $user = User::whereIn('phone', $this->candidatePhones($username))->first();

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

    private function syncStudentIdentity(User $user, ?string $username): void
    {
        if (! $username) {
            return;
        }

        if (! $user->hasRole(UserRole::STUDENT->value) && ! $user->studentProfile()->exists()) {
            return;
        }

        $user->assignRole(UserRole::STUDENT->value);

        if ($user->hasRole(UserRole::SUPER_ADMIN->value)) {
            $user->removeRole(UserRole::SUPER_ADMIN->value);
        }

        StudentProfile::updateOrCreate(
            ['user_id' => $user->id],
            ['student_number' => $username]
        );
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

    private function candidatePhones(string $username): array
    {
        $digits = preg_replace('/\D+/', '', $username);

        if (! $digits) {
            return [$username];
        }

        $withoutCountryCode = str_starts_with($digits, '970')
            ? '0'.substr($digits, 3)
            : null;

        return array_values(array_unique(array_filter([
            $username,
            $digits,
            $withoutCountryCode,
            str_starts_with($digits, '0') ? substr($digits, 1) : null,
            str_starts_with($digits, '0') ? '970'.substr($digits, 1) : null,
            str_starts_with($digits, '0') ? '+970'.substr($digits, 1) : null,
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

    private function ensureUserIsAllowedByKeycloakRoles(array $payload): void
    {
        $roles = data_get($payload, 'realm_access.roles', []);

        if (! is_array($roles)) {
            return;
        }

        if (in_array(self::TEMPORARILY_BLOCKED_ROLE, $roles, true)) {
            throw ValidationException::withMessages([
                'auth' => self::TEMPORARILY_BLOCKED_MESSAGE,
            ]);
        }
    }

    private function decodeTokenPayload(string $token): array
    {
        $payload = explode('.', $token)[1] ?? '';
        $payload = strtr($payload, '-_', '+/');
        $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);

        return json_decode(base64_decode($payload), true) ?: [];
    }
}
