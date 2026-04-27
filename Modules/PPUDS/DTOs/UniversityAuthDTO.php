<?php

namespace Modules\PPUDS\DTOs;

readonly class UniversityAuthDTO
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public int $expiresIn,
        public array $userData = []
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            accessToken: $data['access_token'] ?? '',
            refreshToken: $data['refresh_token'] ?? '',
            expiresIn: $data['expires_in'] ?? 0,
            userData: $data['user_data'] ?? [],
        );
    }
}
