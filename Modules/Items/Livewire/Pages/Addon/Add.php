<?php

namespace Modules\Items\Livewire\Pages\Addon;

use App\View\Components\AppLayout;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Repeater;
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
use Masmerise\Toaster\Toaster;
use Modules\Core\Entities\User;
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\Items\Entities\Addon;
use Modules\Items\Entities\AddonOption;
use Modules\Items\Entities\Category;
use Modules\Items\Enums\AddonType;
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
                        Section::make()
                            ->columnSpan(2)
                            ->schema([
                                Grid::make(1)
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('name')
                                                    ->required()
                                                    ->label(__('Name')),
                                                Select::make('type')
                                                    ->required()
                                                    ->options(AddonType::class)
                                                    ->label(__('Slug')),
                                                Repeater::make('addonOptions')
                                                    ->columnSpanFull()
                                                    ->label(__('Options'))
                                                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? __('New Option'))                                                    ->schema([
                                                        Grid::make(2)
                                                            ->schema([
                                                                TextInput::make('name')
                                                                    ->columnSpan(1)
                                                                    ->required()
                                                                    ->live(debounce: 500)
                                                                    ->label(__('Name')),
                                                                TextInput::make('price')
                                                                    ->columnSpan(1)
                                                                    ->required()
                                                                    ->label(__('Price')),
                                                                Checkbox::make('is_default')
                                                                    ->label(__('Is Default')),
                                                                Checkbox::make('is_quantifiable')
                                                                    ->label(__('Is Quantifiable')),
                                                                Textarea::make('description')
                                                                    ->columnSpanFull()
                                                                    ->label(__('Description')),
                                                                SpatieMediaLibraryFileUpload::make('image')
                                                                    ->required()
                                                                    ->image()
                                                                    ->model(AddonOption::class)
                                                                    ->disk('addon_option')
                                                                    ->directory('addon_option')
                                                                    ->collection('addon_option')
                                                                    ->label(__('Image'))
                                                                    ->imageResizeMode('cover')
                                                                    ->imageCropAspectRatio('1:1')
                                                                    ->imageResizeTargetWidth('400')
                                                                    ->imageResizeTargetHeight('400')
                                                                    ->maxSize(10000),
                                                            ])
                                                    ]),
                                                Textarea::make('description')
                                                    ->columnSpanFull()
                                                    ->label(__('Description'))
                                            ]),
                                    ]),
                            ]),

//                        Section::make()
//                            ->columnSpan(1)
//                            ->schema([
//                                SpatieMediaLibraryFileUpload::make('image')
//                                ->required()
//                                ->image()
//                                ->disk('items')
//                                    ->directory('addons')
//                                ->collection('addon')
//                                ->label(__('Image'))
//                                ->imageResizeMode('cover')
//                                ->imageCropAspectRatio('1:1')
//                                ->imageResizeTargetWidth('400')
//                                ->imageResizeTargetHeight('400')
//                                ->maxSize(10000),
//                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function rules(): array
    {
        return [
            'data.name' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'data.name.required' => __('Name is required'),
        ];
    }


    public function save()
    {
        $this->validate();

        $locale = $this->data['locale'] = app()->getLocale();
        $this->data['created_by'] = auth()->user()->id;

        $addon = Addon::create($this->data);

//        $addon->options()->createMany($this->data['options']);

//        foreach ($this->data['options'] as $option) {
//            $addon->options()->create($option);
//            if (isset($option['image'])) {
//                $addon->options()->addImage($option['image']);
//            }
//        }

        foreach ($this->data['addonOptions'] as $option) {
            $addonOption = $addon->addonOptions()->updateOrCreate(
                ['id' => $option['id'] ?? null],
                \Illuminate\Support\Arr::except($option, 'id')
            );

            if (isset($option['image'])) {
                $addonOption->addImage($option['image']);
            }
        }

//        if (isset($this->data['image'])) {
//            $addon->addImage($this->data['image']);
//        }

        Toaster::success(__('Addon added successfully'));

        $this->redirectRoute('addons.index');
    }

    public function render()
    {
        return view('items::livewire.pages.addon.add')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Addons List'), 'url' => route('addons.index')],
                ['title' => __('Add Addon'), 'url' => route('addons.add')],
            ]
        ]);
    }
}
