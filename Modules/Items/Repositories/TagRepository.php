<?php

namespace Modules\Items\Repositories;

use Modules\Items\Entities\Tag;

class TagRepository
{
    public function renderTag(Tag $tag): string
    {
        $text_color = $tag->text_color;
        $background_color = $tag->background_color;

        return "<span class='px-2 py-1 rounded text-sm font-medium' style='color: " . $text_color . "; background-color: " . $background_color . ";'>" .
            e($tag->name) .
            "</span>";
    }
}
