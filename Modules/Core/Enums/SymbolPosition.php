<?php

namespace Modules\Core\Enums;

enum SymbolPosition: string
{
    case BEFORE = 'before';
    case AFTER = 'after';

    public function label(): string
    {
        return match ($this) {
            self::BEFORE => 'before',
            self::AFTER => 'after',
        };
    }

    public function value(): string
    {
        return match ($this) {
            self::BEFORE => 'before',
            self::AFTER => 'after',
        };
    }
}
