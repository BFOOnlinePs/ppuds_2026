<?php

namespace Modules\Content\Enums;

use Filament\Support\Contracts\HasLabel;

enum PageStatus: int implements HasLabel
{
    case PUBLISHED = 1;
    case DRAFT = 2;

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PUBLISHED => 'Published',
            self::DRAFT => 'Draft',
        };
    }
}
