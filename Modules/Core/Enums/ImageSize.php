<?php

namespace Modules\Core\Enums;

enum ImageSize
{
    case SMALL;
    case MEDIUM;
    case LARGE;

    public function width(): int
    {
        return match($this) {
            self::SMALL => 400,
            self::MEDIUM => 800,
            self::LARGE => 1200,
        };
    }

    public function height(): int
    {
        return match($this) {
            self::SMALL => 300,
            self::MEDIUM => 600,
            self::LARGE => 900,
        };
    }
}
