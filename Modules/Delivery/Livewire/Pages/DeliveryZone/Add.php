<?php

namespace Modules\Delivery\Livewire\Pages\DeliveryZone;

use App\View\Components\AppLayout;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Hash;
use Illuminate\Support\Str;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Livewire\Component;
use Modules\Branch\Entities\Branch;
use Modules\Core\Entities\User;
use Modules\Core\Filament\Forms\Components\MapPicker;
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\Items\Entities\Category;
use Modules\Items\Enums\CategoryStatus;
use Spatie\Permission\Models\Role;

class Add extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    public function mount()
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
               Grid::make(3)
                    ->schema([
                        Section::make('')
                            ->columnSpan(2)
                            ->schema([
                                MapPicker::make('location')
                                    ->reactive()
                                    ->extraAttributes(['wire:ignore'])
                                    ->afterStateUpdated(fn($state) => $this->data['location'] = $state)
                            ]),

                        Section::make(__("Branch"))
                            ->columnSpan(1)
                            ->schema([
                                Select::make('branch_id')
                                    ->label(__('Branch'))
                                    ->required()
                                    ->searchable()
                                    ->options(Branch::get()->pluck('name', 'id'))
                            ])
                    ])
            ])
            ->statePath('data');
    }

    public function rules(): array
    {
        return [
            'data.name' => ['required', 'string', 'max:255'],
            'data.slug' => ['required', 'string', 'max:255'],
            'data.image' => [
                'required',
                'image',
                'mimes:jpeg,jpg,png,gif,webp',
                'max:2048',
            ]
        ];
    }

    public function messages(): array
    {
        return [
            'data.name.required' => __('Name is required'),
            'data.slug.required' => __('Slug is required'),
            'data.image.required' => __('Image is required'),
            'data.image.image' => __('The file must be a valid image.'),
            'data.image.mimes' => __('Image must be: JPG, PNG, GIF, or WebP.'),
            'data.image.max' => __('Image size must not exceed 3MB.'),
            'data.image.dimensions' => __('Image must be at least 200x200 pixels.'),
        ];
    }


    public function save()
    {
        $this->validate();

        $locale = $this->data['locale'] = app()->getLocale();
        $this->data['created_by'] = auth()->user()->id;

        $category = Category::create($this->data);

        if (isset($this->data['image'])) {
            $category->addImage($this->data['image']);
        }
        $this->redirectRoute('categories.index');
    }

    public function render()
    {
        return view('items::livewire.pages.category.add')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Categories List'), 'url' => route('categories.index')],
                ['title' => __('Add Category'), 'url' => route('categories.add')],
            ]
        ]);
    }
}
