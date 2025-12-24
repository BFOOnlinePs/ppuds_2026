<?php

namespace Modules\Core\Enums;

enum ImageQuality: int
{
    case LOW = 50;
    case MEDIUM = 75;
    case HIGH = 90;
    case ULTRA = 100;
}
