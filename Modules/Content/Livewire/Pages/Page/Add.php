<?php

namespace Modules\Content\Livewire\Pages\Page;

use App\View\Components\AppLayout;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\RichEditor;
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
use Modules\Content\Entities\Page;
use Modules\Core\Entities\User;
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

                        Section::make()
                            ->columnSpanFull()
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
                                                    ->unique(Page::class, 'slug', ignoreRecord: true),
                                            ]),
                                            RichEditor::make('content')
                                                ->required()
                                                ->label(__('Content')),
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
            'data.content' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'data.name.required' => __('Name is required'),
            'data.slug.required' => __('Slug is required'),
            'data.content.required' => __('Content is required'),
        ];
    }


    public function save()
    {
        $this->validate();

        $locale = $this->data['locale'] = app()->getLocale();
        $this->data['created_by'] = auth()->user()->id;

        $page = Page::create($this->data);

        $this->redirectRoute('pages.index');
    }

    public function render()
    {
        return view('content::livewire.pages.page.add')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Pages List'), 'url' => route('pages.index')],
                ['title' => __('Add Page'), 'url' => route('pages.add')],
            ]
        ]);
    }
}
