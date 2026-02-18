<?php

namespace Modules\PPUDS\Livewire\Pages\Survey;

use App\View\Components\AppLayout;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
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
use Modules\PPUDS\Entities\Survey;
use Modules\PPUDS\Entities\SurveyQuestion;
use Modules\PPUDS\Enums\SurveyQuestionType;

class Add extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    public ?array $data = [];

    public function mount()
    {
        $this->form->fill([
            'is_active' => true,
            'start_date' => now(),
            'questions' => [
                [
                    'type' => SurveyQuestionType::TEXT->value,
                    'is_required' => true,
                ],
            ],
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->model(Survey::class)
            ->schema([
                // --- Section 1: Survey Basic Details ---
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

                                DatePicker::make('start_date')
                                    ->label(__('Start Date'))
                                    ->required()
                                    ->default(now()),

                                DatePicker::make('end_date')
                                    ->label(__('End Date'))
                                    ->afterOrEqual('start_date'),

                                Toggle::make('is_active')
                                    ->label(__('Published & Active'))
                                    ->default(true)
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
                                Grid::make(12)->schema([
                                    // 1. Question Type
                                    Select::make('type')
                                        ->label(__('Type'))
                                        ->options(SurveyQuestionType::options())
                                        ->default(SurveyQuestionType::TEXT->value)
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(fn ($state, callable $set) => ! SurveyQuestionType::from($state)->hasOptions() ? $set('options', []) : null
                                        )
                                        ->columnSpan(3),

                                    // 2. Question Text
                                    TextInput::make('content')
                                        ->label(__('Question Text'))
                                        ->required()
                                        ->columnSpan(7),

                                    // 3. Is Required
                                    Toggle::make('is_required')
                                        ->label(__('Required'))
                                        ->default(true)
                                        ->inline(false)
                                        ->columnSpan(2),

                                    FileUpload::make('attachment')
                                        ->label(__('Attachment (Image/File)'))
                                        ->disk('public')
                                        ->directory('survey-attachments-temp')
                                        ->image()
                                        ->imageEditor()
                                        ->visible(function (Get $get) {
                                            return $get('type') == SurveyQuestionType::FILE->value;
                                        })
                                        ->columnSpanFull(),
                                ]),

                                // --- Options Repeater (Nested) ---
                                Repeater::make('options')
                                    ->label(__('Answer Options'))
                                    ->visible(function (Get $get) {
                                        $typeVal = $get('type');

                                        return $typeVal && SurveyQuestionType::tryFrom($typeVal)?->hasOptions();
                                    })
                                    ->schema([
                                        TextInput::make('content')
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
        $this->authorize('Survey Create');

        $this->validate();

        $state = $this->data;

        $state['created_by'] = auth()->id();

        $survey = Survey::create($state);

        $questionOrder = 1;

        // لاحظ: لم نعد نستخدم $index هنا
        foreach ($state['questions'] as $qData) {

            /** @var SurveyQuestion $question */
            $question = $survey->questions()->create([
                'content' => $qData['content'],
                'type' => $qData['type'],
                'is_required' => $qData['is_required'],
                'sort_order' => $questionOrder++,
                
            ]);

            $enumType = SurveyQuestionType::tryFrom($qData['type']);

            if ($enumType && $enumType->hasOptions() && ! empty($qData['options'])) {

                $optionOrder = 1;

                foreach ($qData['options'] as $optData) {
                    $question->options()->create([
                        'content' => $optData['content'],
                        'sort_order' => $optionOrder++,
                    ]);
                }
            }
        }

        Toaster::success(__('Survey created successfully'));

        $this->redirect(route('surveys.index'));

    }

    public function render()
    {
        // تأكد من وجود ملف الـ View: modules/ppuds/resources/views/livewire/pages/survey/add.blade.php
        return view('ppuds::livewire.pages.survey.add')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Surveys'), 'url' => route('surveys.index')], // تأكد من الراوت
                ['title' => __('New Survey'), 'url' => '#'],
            ],
        ]);
    }
}
