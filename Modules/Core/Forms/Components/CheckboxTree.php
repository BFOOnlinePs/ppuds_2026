<?php

namespace Modules\Core\Forms\Components;

use Filament\Forms\Components\Field;

class CheckboxTree extends Field
{
    protected string $view = 'core::components.forms.checkbox-tree';

    protected $categories = [];

    public function categories($categories): static
    {
        $this->categories = $categories;

        return $this;
    }

    public function getCategories(): array
    {
        return $this->evaluate($this->categories);
    }
}
