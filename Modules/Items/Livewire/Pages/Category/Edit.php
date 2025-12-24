<?php

namespace Modules\Items\Livewire\Pages\Category;

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
use Modules\Core\Entities\User;
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\Items\Entities\Category;
use Modules\Items\Enums\CategoryStatus;
use Spatie\Permission\Models\Role;

class Edit extends Component implements HasForms
{
    use InteractsWithForms;

    public Category $record;
    public ?array $data = [];

    public function mount($category)
    {
        $this->record = Category::with(['translations', 'media'])->findOrFail($category);
        $this->data = $this->record->toArray();
        $this->form->fill($this->data);
    }

    public function form(Form $form): Form
    {
        return $form
            ->model($this->record)
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
                                                    ->label(__('Name'))
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(function (Set $set, ?string $state) {
                                                        $set('slug', Str::slug($state));
                                                    }),
                                                TextInput::make('slug')
                                                    ->required()
                                                    ->label(__('Slug'))
                                                    ->unique(Category::class, 'slug', ignoreRecord: true),
                                            ]),
                                        Grid::make(1)
                                            ->schema([
                                                Select::make('parent_id')
                                                    ->label(__('Parent Category'))
                                                    ->options(function () {
                                                        return Category::with('translations') // للتأكد من تحميل الترجمة
                                                            ->translated()
                                                            ->where('status', CategoryStatus::ACTIVE)
                                                            ->get()
                                                            ->mapWithKeys(function ($category) {
                                                                return [$category->id => $category->name];
                                                            });
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

                        Section::make()
                            ->columnSpan(1)
                            ->schema([
                                SpatieMediaLibraryFileUpload::make('image')
                                    ->required()
                                    ->image()
                                    ->disk('items')
                                    ->collection('category')
                                    ->label(__('Image'))
                                    ->imageResizeMode('cover')
                                    ->imageCropAspectRatio('1:1')
                                    ->imageResizeTargetWidth('400')
                                    ->imageResizeTargetHeight('400')
                                    ->maxSize(2048),
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

        $category = Category::findOrFail($this->data['id']);

        $category->update($this->data);

        if (isset($this->data['image'])) {
            $category->addImage($this->data['image']);
        }
        $this->redirectRoute('categories.index');
    }

    public function render()
    {
        return view('core::livewire.pages.users.add')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Categories List'), 'url' => route('categories.index')],
                ['title' => __('Edit Category'), 'url' => route('categories.edit', $this->data['id'])],
            ]
        ]);
    }
}
