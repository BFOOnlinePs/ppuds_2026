<?php

namespace Modules\PPUDS\Interfaces;

use Modules\PPUDS\DTOs\UniversityAuthDTO;

interface UniversityAuthServiceInterface {
    public function getAccessToken(string $username, string $password): UniversityAuthDTO;
}
