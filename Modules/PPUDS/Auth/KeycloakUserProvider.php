<?php

namespace Modules\PPUDS\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

class KeycloakUserProvider extends EloquentUserProvider
{
    public function retrieveByKeycloakToken(object $token, array $credentials): ?Authenticatable
    {
        $username = $this->cleanIdentifier(
            $token->preferred_username
                ?? $token->username
                ?? $credentials['username']
                ?? null
        );

        $email = $this->cleanEmail($token->email ?? $credentials['email'] ?? null);

        if ($username) {
            $user = $this->newModelQuery()
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

        return $this->newModelQuery()
            ->whereIn('email', $candidateEmails)
            ->first();
    }

    private function candidateEmails(?string $username, ?string $email): array
    {
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
}
