<?php

namespace Modules\Items\Livewire\Pages\Attribute;

use App\View\Components\AppLayout;
use Closure;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Hash;
use Illuminate\Support\Str;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Livewire\Component;
use Modules\Core\Entities\User;
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\Items\Entities\Attribute;
use Modules\Items\Entities\AttributeTranslation;
use Modules\Items\Entities\Category;
use Modules\Items\Enums\AttributeType;
use Modules\Items\Enums\CategoryStatus;
use Spatie\Permission\Models\Role;

class Edit extends Component implements HasForms
{
    use InteractsWithForms;

    public Attribute $attribute;
    public ?array $data = [];

    public function mount($attribute)
    {
        $this->attribute = $attribute->findOrFail($attribute->id);
        $this->data = $this->attribute->toArray();
        $this->form->fill($this->data);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(1)
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
                                                    ->label(__('Name'))
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(function (Set $set, ?string $state) {
                                                        // $set('slug', Str::slug($state));
                                                    }),
                                                TextInput::make('slug')
                                                    ->required()
                                                    ->label(__('Slug'))
                                                    ->unique(Category::class, 'slug', ignoreRecord: true),
                                            ]),
                                        Grid::make(1)
                                            ->schema([
                                                Select::make('type')
                                                    ->label(__('Attribute Type'))
                                                    ->options(function () {
                                                        return AttributeType::options();
                                                    })
                                                    ->searchable()
                                                    ->preload()
                                                    ->placeholder(__('Select Parent Category'))
                                                    ->default(null)
                                                    ->reactive()
                                                    ->afterStateUpdated(function (Set $set, ?int $state) {
                                                        if ($state) {
                                                            $set('locale', app()->getLocale());
                                                        }
                                                    }),
                                            ]),
                                        Grid::make(1)
                                            ->schema([
                                                Textarea::make('description')
                                                    ->label(__('Description'))
                                            ]),
                                    ]),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }


    public function rules(): array
    {
        return [
            'data.name' => ['required', 'string', 'max:255'],
            'data.slug' => ['required', 'string', 'max:255'],
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

        $this->attribute->update($this->data);

        // if (isset($this->data['image'])) {
        //     $category->addImage($this->data['image']);
        // }
        $this->redirectRoute('attributes.index');
    }

    public function render()
    {
        return view('items::livewire.pages.attribute.add')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Attributes List'), 'url' => route('attributes.index')],
                ['title' => __('Add Attribute'), 'url' => route('attributes.add')],
            ]
        ]);
    }
}
