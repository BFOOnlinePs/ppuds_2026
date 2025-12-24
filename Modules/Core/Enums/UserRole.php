<?php

namespace Modules\Core\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserRole: String implements HasLabel
{
    case SUPER_ADMIN = 'Super Admin';
    case ADMIN = 'Admin';
    case USER = 'User';
    case CUSTOMER = 'Customer';

    public function getLabel(): ?string
    {
        return $this->name;
    }
}
