<?php

namespace Modules\Core\Filament\Forms\Components;

use Filament\Forms\Components\Field;
use Livewire\Attributes\Computed;
use Modules\Items\Entities\Category;
use Modules\Items\Enums\CategoryStatus;

class CategoryTreeSelect extends Field
{
    protected string $view = 'core::components.forms.category-tree-select';

    public $categories = [];

    #[Computed()]
    public function categories(): array
    {
        return Category::query()->where('status', CategoryStatus::ACTIVE)->get()->toArray();
    }
}
