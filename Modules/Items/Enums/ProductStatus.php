<?php

namespace Modules\Items\Enums;

enum ProductStatus : int
{
    case DRAFT = 1;
    case PUBLISHED = 2;
    case ARCHIVED = 3;

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PUBLISHED => 'Published',
            self::ARCHIVED => 'Archived',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'secondary',
            self::PUBLISHED => 'success',
            self::ARCHIVED => 'danger',
        };
    }
}
