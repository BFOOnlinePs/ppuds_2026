<?php

namespace Modules\PPUDS\Livewire\Pages\Survey;

use App\View\Components\AppLayout;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Core\Enums\UserRole;
use Modules\PPUDS\Entities\Major;
use Modules\PPUDS\Entities\Survey;
use Modules\PPUDS\Entities\SurveyQuestion;
use Modules\PPUDS\Entities\SurveyQuestionOption;
use Modules\PPUDS\Enums\SurveyQuestionType;

class Edit extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    public ?array $data = [];

    public Survey $record;

    public function mount(Survey $survey)
    {
        $this->record = $survey->load('questions.options');

        $surveyData = $this->record->toArray();

        $this->form->fill($surveyData);
    }

    public function form(Form $form): Form
    {
        return $form
            ->model($this->record)
            ->schema([
                Section::make(__('Survey Details'))
                    ->description(__('Basic information about the survey context.'))
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                        ])
                            ->schema([
                                TextInput::make('title')
                                    ->label(__('Survey Title'))
                                    ->required()
                                    ->maxLength(255),

                                Select::make('serve_group')
                                    ->label(__('Target Audience'))
                                    ->options(UserRole::options())
                                    ->required()
                                    ->searchable()
                                    ->placeholder('e.g. 4th Year Students'),

                                Select::make('major_id')
                                    ->label(__('Target Major'))
                                    ->options(fn () => Major::get()->pluck('name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->nullable(),

                                DatePicker::make('start_date')
                                    ->label(__('Start Date'))
                                    ->required(),

                                DatePicker::make('end_date')
                                    ->label(__('End Date'))
                                    ->afterOrEqual('start_date'),

                                Toggle::make('is_active')
                                    ->label(__('Published & Active'))
                                    ->inline(false)
                                    ->onColor('success'),
                            ]),

                        Textarea::make('description')
                            ->label(__('Description & Instructions'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                // --- Section 2: Questions Builder ---
                Section::make(__('Questions Builder'))
                    ->description(__('Define your questions. Options will appear based on the question type.'))
                    ->schema([
                        Repeater::make('questions')
                            ->label(__('Questions List'))
                            ->schema([
                                // حقل مخفي للاحتفاظ بـ ID السؤال عند التعديل
                                Hidden::make('id'),

                                Grid::make(12)->schema([
                                    // 1. Question Type
                                    Select::make('type')
                                        ->label(__('Type'))
                                        ->options(SurveyQuestionType::options())
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(fn ($state, callable $set) => ! SurveyQuestionType::from($state)->hasOptions() ? $set('options', []) : null)
                                        ->columnSpan(3),

                                    // 2. Question Text
                                    TextInput::make('content')
                                        ->label(__('Question Text'))
                                        ->required()
                                        ->columnSpan(7),

                                    // 3. Is Required
                                    Toggle::make('is_required')
                                        ->label(__('Required'))
                                        ->inline(false)
                                        ->columnSpan(2),

                                    // FileUpload::make('attachment')
                                    //     ->label(__('Attachment (Image/File)'))
                                    //     ->disk('public')
                                    //     ->directory('survey-attachments-temp')
                                    //     ->image()
                                    //     ->imageEditor()
                                    //     ->visible(function (Get $get) {
                                    //         return $get('type') == SurveyQuestionType::FILE->value;
                                    //     })
                                    //     ->columnSpanFull(),
                                ]),

                                // --- Options Repeater (Nested) ---
                                Repeater::make('options')
                                    ->label(__('Answer Options'))
                                    ->visible(function (Get $get) {
                                        $typeVal = $get('type');

                                        return $typeVal && SurveyQuestionType::tryFrom($typeVal)?->hasOptions();
                                    })
                                    ->schema([
                                        Hidden::make('id'),
                                        TextInput::make('text')
                                            ->label(__('Option Text'))
                                            ->required(),
                                    ])
                                    ->grid(2)
                                    ->defaultItems(2)
                                    ->addActionLabel(__('Add Option'))
                                    ->reorderableWithButtons()
                                    ->columnSpanFull(),
                            ])
                            ->cloneable()
                            ->collapsible()
                            ->orderColumn('sort_order')
                            ->itemLabel(fn (array $state): ?string => $state['content'] ?? __('New Question'))
                            ->addActionLabel(__('Add New Question')),
                    ]),
            ])
            ->statePath('data');
    }

    public function save()
    {
        $this->authorize('Survey Update');

        $this->validate();

        $state = $this->data;

        // 1. تحديث البيانات الأساسية للاستبيان
        $this->record->update([
            'title' => $state['title'],
            'serve_group' => $state['serve_group'],
            'major_id' => $state['major_id'] ?? null,
            'start_date' => $state['start_date'],
            'end_date' => $state['end_date'],
            'is_active' => $state['is_active'],
            'description' => $state['description'] ?? null,
        ]);

        $submittedQuestionIds = [];
        $questionOrder = 1;

        foreach ($state['questions'] ?? [] as $qData) {
            $questionId = $qData['id'] ?? null;

            if ($questionId) {
                $question = SurveyQuestion::find($questionId);
                $question->update([
                    'content' => $qData['content'],
                    'type' => $qData['type'],
                    'is_required' => $qData['is_required'],
                    'sort_order' => $questionOrder++,
                ]);
            } else {
                $question = $this->record->questions()->create([
                    'content' => $qData['content'],
                    'type' => $qData['type'],
                    'is_required' => $qData['is_required'],
                    'sort_order' => $questionOrder++,
                ]);
            }

            $submittedQuestionIds[] = $question->id;

            $enumType = SurveyQuestionType::tryFrom($qData['type']);
            $submittedOptionIds = [];

            if ($enumType && $enumType->hasOptions() && ! empty($qData['options'])) {
                $optionOrder = 1;

                foreach ($qData['options'] as $optData) {
                    $optionId = $optData['id'] ?? null;

                    if ($optionId) {
                        // تحديث خيار موجد
                        $option = SurveyQuestionOption::find($optionId);
                        $option->update([
                            'text' => $optData['text'],
                            'sort_order' => $optionOrder++,
                        ]);
                    } else {
                        $option = $question->options()->create([
                            'text' => $optData['text'],
                            'sort_order' => $optionOrder++,
                        ]);
                    }
                    $submittedOptionIds[] = $option->id;
                }
            }

            $question->options()->whereNotIn('id', $submittedOptionIds)->delete();
        }

        $this->record->questions()->whereNotIn('id', $submittedQuestionIds)->delete();

        Toaster::success(__('Survey updated successfully'));

        $this->redirect(route('surveys.index'));
    }

    public function render()
    {
        return view('ppuds::livewire.pages.survey.edit')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Surveys'), 'url' => route('surveys.index')],
                ['title' => __('Edit Survey'), 'url' => '#'],
            ],
        ]);
    }
}
