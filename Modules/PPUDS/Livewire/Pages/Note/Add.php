<?php

namespace Modules\PPUDS\Livewire\Pages\Note;

use App\View\Components\AppLayout;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\PPUDS\Entities\Note;

class Add extends Component implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    public ?array $data = [];

    public function mount()
    {
        $this->form->fill([
            'note_date' => now()->format('Y-m-d'), // افتراضياً تاريخ اليوم
            'category' => 'academic',
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->model(Note::class)
            ->schema([
                Grid::make(['default' => 1, 'lg' => 3])
                ->schema([
                    // --- العمود الرئيسي (يسار - يأخذ مساحة 2) ---
                    Group::make()
                        ->columnSpan(['lg' => 2])
                        ->schema([
                            Section::make(__('Note Content'))
                                ->icon('solar-pen-new-square-bold-duotone')
                                ->schema([
                                    TextInput::make('name')
                                        ->label(__('Note Title'))
                                        ->required()
                                        ->maxLength(255)
                                        ->placeholder(__('Enter a brief title for your note...'))
                                        ->prefixIcon('solar-letter-opened-linear'),

                                    RichEditor::make('content')
                                        ->label(__('Details'))
                                        ->required()
                                        ->placeholder(__('Write your notes here...'))
                                        ->columnSpanFull(),
                                ]),
                        ]),

                    // --- العمود الجانبي (يمين - يأخذ مساحة 1) ---
                    Group::make()
                        ->columnSpan(['lg' => 1])
                        ->schema([
                            // قسم التصنيف والتاريخ
                            Section::make(__('Settings'))
                                ->icon('solar-settings-bold-duotone')
                                ->schema([
                                    DatePicker::make('note_date')
                                        ->label(__('Note Date'))
                                        ->required()
                                        ->native(false)
                                        ->prefixIcon('solar-calendar-date-linear')
                                        ->displayFormat('Y-m-d'),


                                    Toggle::make('is_pinned')
                                        ->label(__('Pin this note'))
                                        ->helperText(__('Pinned notes appear at the top.'))
                                        ->default(false),
                                ]),

                        ]),
                ]),
            ])
            ->statePath('data');
    }

    protected function messages(): array
    {
        return [
            'data.name.required' => __('Please enter a title for the note.'),
            'data.content.required' => __('The note content cannot be empty.'),
            'data.note_date.required' => __('Please select a date.'),
        ];
    }

    public function save()
    {
        // $this->authorize("Note Create");

        $this->validate();

        $noteData = $this->data;
        $noteData['user_id'] = auth()->id(); // ربط الملاحظة بالمستخدم الحالي

        Note::create($noteData);

        Toaster::success(__('Note created successfully'));

        $this->redirect(route('notes.index'));
    }

    public function render()
    {
        return view('ppuds::livewire.pages.note.add')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('My Notes'), 'url' => route('notes.index')],
                ['title' => __('New Note'), 'url' => '#'],
            ]
        ]);
    }
}