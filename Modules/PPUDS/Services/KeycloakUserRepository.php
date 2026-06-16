<?php

namespace Modules\PPUDS\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Core\Entities\User;

class KeycloakUserRepository
{
    public function retrieveByToken(object $token, array $credentials): ?User
    {
        $username = $this->cleanIdentifier(
            $token->preferred_username
                ?? $token->username
                ?? $credentials['username']
                ?? null
        );

        $email = $this->cleanEmail($token->email ?? $credentials['email'] ?? null);

        if (! $username && ! $email) {
            return null;
        }

        $user = $this->findUser($username, $email);
        $clientRoles = $token->resource_access->{'Dual-Studies-Laravel'}->roles ?? [];

        if (! $user && $username) {
            return DB::transaction(function () use ($username, $email, $token, $clientRoles) {
                $newUser = User::create([
                    'name' => $token->name ?? $token->preferred_username ?? 'Student',
                    'email' => $email ?: "{$username}@ppu.edu.ps",
                    'password' => Hash::make(Str::random(32)),
                ]);

                $newUser->syncRoles($clientRoles);

                if ($newUser->hasRole('DualStudiesStudent') || in_array('Student', $clientRoles, true)) {
                    $newUser->studentProfile()->create([
                        'student_number' => $username,
                    ]);
                }

                return $newUser;
            });
        }

        if (! $user) {
            return null;
        }

        $cacheKey = "user_{$user->id}_keycloak_roles";
        $rolesHash = md5(json_encode($clientRoles));

        if (Cache::get($cacheKey) !== $rolesHash) {
            $user->syncRoles($clientRoles);
            Cache::put($cacheKey, $rolesHash, now()->addHours(2));
        }

        return $user;
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

        $candidateEmails = array_values(array_unique(array_filter([
            $email,
            $username,
            $username ? "{$username}@ppu.edu.ps" : null,
            $username ? "{$username}@ppu.edu" : null,
        ])));

        if ($candidateEmails === []) {
            return null;
        }

        return User::whereIn('email', $candidateEmails)->first();
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
}
