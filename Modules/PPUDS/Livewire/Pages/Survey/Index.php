<?php

namespace Modules\PPUDS\Livewire\Pages\Survey;

use App\View\Components\AppLayout;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\Action as ActionsAction;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\PPUDS\Entities\Survey;
use Modules\PPUDS\Entities\SurveyAnswer;

class Index extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Survey::query()->with('translations'))
            ->columns([

                TextColumn::make('title')
                    ->label(__('Title'))
                    ->searchable(),

                TextColumn::make('serve_group')
                    ->label(__('Target Group'))
                    ->formatStateUsing(fn ($state) => $state ? \Modules\Core\Enums\UserRole::from($state)->getLabel() : '-')
                    ->searchable(),

                TextColumn::make('semester')
                    ->label(__('Semester'))
                    ->badge()
                    ->searchable(),

                TextColumn::make('year')
                    ->label(__('Year'))
                    ->searchable(),

                TextColumn::make('start_date')
                    ->label(__('Start Date'))
                    ->dateTime('Y-m-d')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('end_date')
                    ->label(__('End Date'))
                    ->dateTime('Y-m-d')
                    ->toggleable(isToggledHiddenByDefault: true),

                ToggleColumn::make('is_active')
                    ->label(__('Active'))
                    ->visible(fn () => ! auth()->user()->hasRole('Student')),

                TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime('Y-m-d')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters($this->getTableFilters(), layout: \Filament\Tables\Enums\FiltersLayout::AboveContent)
            ->filtersFormColumns(5)
            ->actions($this->getTableActions())
            ->headerActions([
                \Modules\Core\Filament\Forms\Components\CreateAction::make('create')
                    ->label(__('Add Survey'))
                    ->url(route('surveys.add'))
                    ->visible(fn () => auth()->user()->can('Survey Create')),
            ])
            ->bulkActions($this->getTableBulkAction());
    }

    protected function getTableFilters(): array
    {
        return [

        ];
    }

    protected function getTableActions(): array
    {
        return [
            InfoAction::make('info')
                ->label('')
                ->visible(fn () => auth()->user()->can('Survey Info')),

            ViewAction::make('view')
                ->label('')
                ->tooltip(__('View'))
                ->modalHeading(__('Survey Details'))
                ->mountUsing(function (\Filament\Forms\Form $form, \Modules\PPUDS\Entities\Survey $record) {
                    $record->load('questions.options');

                    $data = $record->toArray();

                    $data['questions'] = $record->questions->map(function ($question) {
                        return [
                            'type' => $question->type,
                            'content' => $question->content,
                            'is_required' => $question->is_required,
                            'options' => $question->options->map(function ($option) {
                                return [
                                    'text' => $option->text,
                                ];
                            })->toArray(),
                        ];
                    })->toArray();

                    $form->fill($data);
                })
                ->form(function ($record) {
                    return [
                        \Filament\Forms\Components\Section::make(__('Basic Information'))
                            ->schema([
                                \Filament\Forms\Components\Grid::make(['default' => 1, 'md' => 2])->schema([
                                    \Filament\Forms\Components\TextInput::make('title')
                                        ->label(__('Survey Title'))
                                        ->disabled(), // تعطيل

                                    \Filament\Forms\Components\Select::make('serve_group')
                                        ->label(__('Target Audience'))
                                        ->options(\Modules\Core\Enums\UserRole::options())
                                        ->disabled(), // تعطيل

                                    \Filament\Forms\Components\DatePicker::make('start_date')
                                        ->label(__('Start Date'))
                                        ->disabled(), // تعطيل

                                    \Filament\Forms\Components\DatePicker::make('end_date')
                                        ->label(__('End Date'))
                                        ->disabled(), // تعطيل

                                    \Filament\Forms\Components\Toggle::make('is_active')
                                        ->label(__('Published & Active'))
                                        ->inline(false)
                                        ->onColor('success')
                                        ->disabled(), // تعطيل
                                ]),

                                \Filament\Forms\Components\Textarea::make('description')
                                    ->label(__('Description & Instructions'))
                                    ->columnSpanFull()
                                    ->disabled(), // تعطيل
                            ]),

                        \Filament\Forms\Components\Section::make(__('Questions & Options'))
                            ->schema([
                                \Filament\Forms\Components\Repeater::make('questions')
                                    ->relationship('questions')
                                    ->label(__('Questions List'))
                                    ->schema([
                                        \Filament\Forms\Components\Grid::make(12)->schema([
                                            \Filament\Forms\Components\Select::make('type')
                                                ->label(__('Type'))
                                                ->options(\Modules\PPUDS\Enums\SurveyQuestionType::options())
                                                ->columnSpan(3)
                                                ->disabled(), // تعطيل

                                            \Filament\Forms\Components\TextInput::make('content')
                                                ->label(__('Question Text'))
                                                ->columnSpan(7)
                                                ->disabled(), // تعطيل

                                            \Filament\Forms\Components\Toggle::make('is_required')
                                                ->label(__('Required'))
                                                ->inline(false)
                                                ->columnSpan(2)
                                                ->disabled(), // تعطيل
                                        ]),

                                        // عرض الخيارات
                                        \Filament\Forms\Components\Repeater::make('options')
                                            ->relationship('options')
                                            ->label(__('Answer Options'))
                                            ->schema([
                                                \Filament\Forms\Components\TextInput::make('text')
                                                    ->label(__('Option Text'))
                                                    ->disabled(), // تعطيل
                                            ])
                                            ->grid(2)
                                            ->addable(false)
                                            ->deletable(false)
                                            ->reorderable(false)
                                            ->columnSpanFull(),
                                    ])
                                    ->addable(false)
                                    ->deletable(false)
                                    ->reorderable(false)
                                    ->collapsible(),
                            ]),
                    ];
                })
                ->modalSubmitAction(false)
                ->visible(fn () => auth()->user()->can('Survey View')),

            ActionsAction::make('survey_submit')
                ->label(__('Submit Survey'))
                ->color('success')
                ->icon('heroicon-s-check')
                ->modalHeading(fn (Model $record) => $record->title)
                ->modalDescription(fn (Model $record) => $record->description)
                ->form(function (Model $record) {
                    $record->loadMissing([
                        'questions.translations',
                        'questions.options.translations',
                    ]);

                    $schema = [];

                    foreach ($record->questions->sortBy('sort_order') as $question) {
                        $options = $question->options->mapWithKeys(function ($option) {
                            return [$option->id => $option->text];
                        })->toArray();

                        $field = match ((int) $question->type) {
                            1 => TextInput::make("question_{$question->id}"),
                            2 => Textarea::make("question_{$question->id}"),
                            3 => Radio::make("question_{$question->id}")->options($options),
                            4 => CheckboxList::make("question_{$question->id}")->options($options),
                            default => TextInput::make("question_{$question->id}"),
                        };

                        $field->label($question->content)
                            ->required($question->is_required);

                        $schema[] = $field;
                    }

                    return $schema;
                })
                ->action(function (Model $record, array $data) {
                    $userId = auth()->id();

                    // 🛡️ طبقة الحماية: التأكد من أن المستخدم لم يقدم الاستبيان مسبقاً (لمنع التكرار إذا تم تجاوز الواجهة)
                    $hasSubmitted = SurveyAnswer::where('survey_id', $record->id)
                        ->where('submitted_by', $userId)
                        ->exists();

                    if ($hasSubmitted) {
                        Notification::make()
                            ->danger()
                            ->title(__('You have already submitted this survey')) // "لقد قمت بتسليم هذا الاستبيان مسبقاً"
                            ->send();

                        return; // إيقاف التنفيذ
                    }

                    $answers = [];
                    $now = now();

                    foreach ($data as $key => $value) {
                        if (str_starts_with($key, 'question_') && $value !== null && $value !== '') {
                            $questionId = (int) str_replace('question_', '', $key);
                            $question = $record->questions->where('id', $questionId)->first();

                            if (is_array($value)) {
                                foreach ($value as $optionId) {
                                    $answers[] = [
                                        'survey_id' => $record->id,
                                        'survey_question_id' => $questionId,
                                        'selected_option_id' => $optionId,
                                        'text_answer' => null,
                                        'submitted_by' => $userId,
                                        'created_at' => $now,
                                        'updated_at' => $now,
                                    ];
                                }
                            } else {
                                $isOption = in_array((int) $question->type, [3, 4]);

                                $answers[] = [
                                    'survey_id' => $record->id,
                                    'survey_question_id' => $questionId,
                                    'selected_option_id' => $isOption ? $value : null,
                                    'text_answer' => $isOption ? null : $value,
                                    'submitted_by' => $userId,
                                    'created_at' => $now,
                                    'updated_at' => $now,
                                ];
                            }
                        }
                    }

                    if (! empty($answers)) {
                        SurveyAnswer::insert($answers);
                    }

                    Notification::make()
                        ->success()
                        ->title(__('Survey submitted successfully'))
                        ->send();
                })
    // 👁️ إخفاء الزر برمجياً إذا كان المستخدم قد أرسل إجابات لهذا الاستبيان من قبل
                ->visible(function (Model $record) {
                    $user = auth()->user();

                    // 1. هل لديه الصلاحية أولاً؟
                    if (! $user->can('Survey Submit')) {
                        return false;
                    }

                    // 2. هل قام بالتسليم مسبقاً؟ (إذا كان الجواب نعم، نرجع false لكي يختفي الزر)
                    $hasSubmitted = SurveyAnswer::where('survey_id', $record->id)
                        ->where('submitted_by', $user->id)
                        ->exists();

                    return ! $hasSubmitted;
                }),

            EditAction::make('edit')
                ->label('')
                ->tooltip(__('Edit'))
                ->url(fn (Survey $record) => route('surveys.edit', $record->id))
                ->visible(fn () => auth()->user()->can('Survey Update')),

            DeleteAction::make('delete')
                ->label('')
                ->tooltip(__('Delete'))
                ->action(function ($record) {
                    $record->delete();
                    Toaster::success(__('Survey record deleted successfully'));
                })
                ->visible(fn () => auth()->user()->can('Survey Delete')),
        ];
    }

    public function getTableBulkAction(): array
    {
        return [
            BulkActionGroup::make([
                BulkAction::make('delete')
                    ->label(__('Delete Selected'))
                    ->icon('solar-trash-bin-trash-bold-duotone')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (Collection $records) => $records->each->delete())
                    ->after(fn () => Toaster::success(__('Selected records deleted successfully')))
                    ->visible(fn () => auth()->user()->can('Survey Delete')),
            ]),
        ];
    }

    public function render()
    {
        return view('ppuds::livewire.pages.survey.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Surveys'), 'url' => route('surveys.index')],
            ],
        ]);
    }
}
