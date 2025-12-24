<?php

namespace Modules\Items\Enums;

enum ProductActive : int
{
    case IS_ACTIVE = 1;
    case IN_ACTIVE = 0;

    public function label(): string
    {
        return match ($this) {
            self::IS_ACTIVE => __('Active'),
            self::IN_ACTIVE => __('Inactive'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::IS_ACTIVE => 'success',
            self::IN_ACTIVE => 'danger',
        };
    }
}
