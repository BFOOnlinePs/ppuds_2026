<?php

namespace Modules\PPUDS\Livewire\Pages\TrainingPlan;

use App\View\Components\AppLayout;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Core\Filament\Forms\Components\CreateAction;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\PPUDS\Entities\Course;
use Modules\PPUDS\Entities\Major;
use Modules\PPUDS\Entities\TrainingPlan;

class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn() => TrainingPlan::query()->with(['major.translations', 'course.translations']))
            ->columns([
                TextColumn::make('major.name')
                    ->label(__('Major'))
                    ->searchable(
                        query: fn(Builder $query, string $search) => $query->whereHas(
                            'major',
                            fn(Builder $majorQuery) => $majorQuery->whereTranslationLike('name', "%{$search}%")
                        )
                    ),
                TextColumn::make('course.name')
                    ->label(__('Course'))
                    ->searchable(
                        query: fn(Builder $query, string $search) => $query->whereHas(
                            'course',
                            fn(Builder $courseQuery) => $courseQuery->whereTranslationLike('name', "%{$search}%")
                        )
                    ),
            ])
            ->filters([
                SelectFilter::make('major_id')
                    ->label(__('Major'))
                    ->options(fn() => Major::get()->pluck('name', 'id'))
                    ->searchable()
                    ->native(false),
                SelectFilter::make('course_id')
                    ->label(__('Course'))
                    ->options(fn() => Course::get()->pluck('name', 'id'))
                    ->searchable()
                    ->native(false),
            ])
            ->actions($this->getTableActions())
            ->headerActions([
                CreateAction::make('create')
                    ->label(__('Add Training Plan'))
                    ->modalHeading(__('Add Training Plan'))
                    ->modalWidth('4xl')
                    ->form(fn() => $this->planFormSchema())
                    ->mutateFormDataUsing(function (array $data) {
                        $data['created_by'] = auth()->id();

                        return $data;
                    })
                    ->visible(fn() => auth()->user()->can('TrainingPlan Create')),
            ]);
    }

    protected function getTableActions(): array
    {
        return [
            ViewAction::make('view')
                ->modalHeading(__('Training Plan'))
                ->modalWidth('4xl')
                ->form(fn() => $this->planFormSchema())
                ->fillForm(fn(TrainingPlan $record) => $this->planFormData($record))
                ->disabledForm()
                ->modalSubmitAction(false)
                ->visible(fn() => auth()->user()->can('TrainingPlan View')),

            EditAction::make('edit')
                ->modalHeading(__('Edit Training Plan'))
                ->modalWidth('4xl')
                ->form(fn() => $this->planFormSchema())
                ->fillForm(fn(TrainingPlan $record) => $this->planFormData($record))
                ->action(function (TrainingPlan $record, array $data) {
                    $record->update($data);
                    Toaster::success(__('Training plan updated successfully'));
                })
                ->visible(fn() => auth()->user()->can('TrainingPlan Update')),

            DeleteAction::make('delete')
                ->action(function (TrainingPlan $record) {
                    $this->authorize('TrainingPlan Delete');
                    $record->delete();
                    Toaster::success(__('Training plan deleted successfully'));
                })
                ->visible(fn() => auth()->user()->can('TrainingPlan Delete')),
        ];
    }

    protected function planFormSchema(): array
    {
        return [
            Grid::make(2)->schema([
                Select::make('major_id')
                    ->label(__('Major'))
                    ->prefixIcon('solar-square-academic-cap-bold-duotone')
                    ->options(fn() => Major::get()->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('course_id')
                    ->label(__('Course'))
                    ->prefixIcon('solar-book-bookmark-bold-duotone')
                    ->options(fn() => Course::get()->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->unique(
                        TrainingPlan::class,
                        'course_id',
                        ignoreRecord: true,
                        modifyRuleUsing: fn(Unique $rule, Get $get) => $rule
                            ->where('major_id', $get('major_id'))
                            ->whereNull('deleted_at')
                    )
                    ->validationMessages([
                        'unique' => __('This major already has a training plan for this course.'),
                    ]),
            ]),
            $this->itemsRepeater('objectives', __('Objectives'), __('Add Objective')),
            $this->itemsRepeater('tasks', __('Tasks'), __('Add Task')),
            $this->itemsRepeater('outcomes', __('Outcomes'), __('Add Outcome')),
            $this->itemsRepeater('subjects', __('Subjects'), __('Add Subject'), required: false),
        ];
    }

    protected function itemsRepeater(string $name, string $label, string $addLabel, bool $required = true): Repeater
    {
        return Repeater::make($name)
            ->label($label)
            ->simple(
                Textarea::make('item')
                    ->rows(2)
                    ->required()
            )
            ->defaultItems($required ? 1 : 0)
            ->minItems($required ? 1 : 0)
            ->required($required)
            ->addActionLabel($addLabel);
    }

    protected function planFormData(TrainingPlan $record): array
    {
        return [
            'major_id' => $record->major_id,
            'course_id' => $record->course_id,
            'objectives' => $record->objectives,
            'tasks' => $record->tasks,
            'outcomes' => $record->outcomes,
            'subjects' => $record->subjects ?? [],
        ];
    }

    public function render()
    {
        return view('ppuds::livewire.pages.training-plan.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Training Plans'), 'url' => route('training-plans.index')],
            ]
        ]);
    }
}
