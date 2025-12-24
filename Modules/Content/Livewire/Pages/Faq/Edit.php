<?php

namespace Modules\Content\Livewire\Pages\Faq;

use App\View\Components\AppLayout;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Illuminate\Support\Str;
use Livewire\Component;
use Modules\Content\Entities\FaqCategory;

class Edit extends Component implements HasForms
{
    use InteractsWithForms;

    public FaqCategory $record;
    public ?array $data = [];

    public function mount($slug)
    {
        $this->record = FaqCategory::with('faqs')->where('slug', $slug)->firstOrFail();
        $this->data = $this->record->toArray();
        $translation = $this->record->translate(app()->getLocale());
        if ($translation) {
            $this->data['name'] = $this->record->name;
        }

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
                                                    ->unique(FaqCategory::class, 'slug', ignoreRecord: true),
                                            ]),
                                        Repeater::make('faqs')
                                            ->schema([
                                                Hidden::make('id'),
                                                TextInput::make('question')
                                                    ->required()
                                                    ->label(__('Question')),
                                                RichEditor::make('answer')
                                                    ->required()
                                                    ->label(__('Answer')),
                                            ])
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
        ];
    }


    public function save()
    {
        $this->validate();

        $locale = $this->data['locale'] = app()->getLocale();

        $this->record->update(collect($this->data)->except('faqs')->toArray());

        $faqs = collect($this->data['faqs'] ?? []);

        $this->record->faqs()->whereNotIn('id', $faqs->pluck('id')->filter())->delete();

        $faqs->each(fn ($faq) =>
        $this->record->faqs()->updateOrCreate(
            ['id' => $faq['id'] ?? null],
            $faq + ['created_by' => auth()->id()]
        )
        );

        $this->redirectRoute('faqs.index');
    }

    public function render()
    {
        return view('content::livewire.pages.faq.edit')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Faqs List'), 'url' => route('faqs.index')],
                ['title' => __('Edit Faq'), 'url' => route('faqs.edit', $this->record->slug)],
            ]
        ]);
    }
}
