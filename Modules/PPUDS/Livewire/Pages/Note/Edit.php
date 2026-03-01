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
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\PPUDS\Entities\Note;

class Edit extends Component implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    public ?array $data = [];
    public $record; // متغير لحمل سجل الملاحظة

    public function mount($note)
    {
        // جلب الملاحظة أو إرجاع 404
        $this->record = Note::findOrFail($note);

        // تعبئة البيانات (بما فيها الحقول المترجمة)
        $this->form->fill([
            'name' => $this->record->name,
            'content' => $this->record->content,
            'note_date' => $this->record->note_date?->format('Y-m-d'),
            'is_pinned' => $this->record->is_pinned,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->model($this->record)
            ->schema([
                Grid::make(['default' => 1, 'lg' => 3])
                ->schema([
                    // --- العمود الرئيسي (يسار - مساحة 2) ---
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
                                        ->prefixIcon('solar-letter-opened-linear'),

                                    RichEditor::make('content')
                                        ->label(__('Details'))
                                        ->required()
                                        ->columnSpanFull(),
                                ]),
                        ]),

                    // --- العمود الجانبي (يمين - مساحة 1) ---
                    Group::make()
                        ->columnSpan(['lg' => 1])
                        ->schema([
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
                                        ,
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
        $this->authorize("Note Update");

        $this->validate();

        $this->record->update($this->data);

        Toaster::success(__('Note updated successfully'));

        $this->redirect(route('notes.index'));
    }

    public function render()
    {
        return view('ppuds::livewire.pages.note.edit')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('My Notes'), 'url' => route('notes.index')],
                ['title' => __('Edit Note'), 'url' => '#'],
            ]
        ]);
    }
}