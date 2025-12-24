<?php

namespace Modules\Content\Livewire\Pages\Faq;

use App\View\Components\AppLayout;
use Filament\Forms\Components\Grid;
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
use Modules\Content\Entities\Page;

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
            ->model(FaqCategory::class)
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
        $this->data['created_by'] = auth()->user()->id;

        $faqCategory = FaqCategory::create($this->data);

        $faqsRaw = $this->data['faqs'] ?? [];



        if (isset($this->data['faqs']) && is_array($this->data['faqs'])) {
            $faqData = collect($this->data['faqs'])->map(function ($item){
                $item['created_by'] = auth()->user()->id;
                return $item;
            })->toArray();

            $faqCategory->faqs()->createMany($faqData);
        }


        $this->redirectRoute('faqs.index');
    }

    public function render()
    {
        return view('content::livewire.pages.faq.add')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Faqs List'), 'url' => route('faqs.index')],
                ['title' => __('Add Faq'), 'url' => route('faqs.add')],
            ]
        ]);
    }
}
