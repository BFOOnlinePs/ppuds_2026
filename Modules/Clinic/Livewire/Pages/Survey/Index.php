<?php

namespace Modules\Clinic\Livewire\Pages\Survey;

use App\View\Components\AppLayout;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Tables\Actions\Action;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Masmerise\Toaster\Toaster;
use Modules\Clinic\Entities\Disease;
use Modules\Clinic\Entities\FoodItem;
use Modules\Clinic\Entities\ServingSize;
use Modules\Clinic\Entities\Survey;
use Modules\Clinic\Enums\QuestionType;
use Modules\Core\Filament\Forms\Components\CreateAction;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Filament\Forms\Components\Toggle;

class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table)
    {
        return $table
            ->query(fn() => Survey::query()->with('createdBy' , 'translations'))
            ->heading(__('Surveys'))
            ->emptyStateHeading(__('No surveys found'))
            ->emptyStateDescription(__('Create a new survey by clicking the button below'))
            ->emptyStateActions([
                CreateAction::make()
                    ->label(__('Add Survey'))
                    ->visible(fn() => auth()->user()->can('Survey Create'))
            ])
            ->columns([
                TextColumn::make('name')
                    ->searchable(
                        query:function (Builder $query, string $search): Builder {
                            return $query->whereTranslationLike('name', '%' . $search . '%');
                        }
                    )
                    ->label(__('Name')),
                TextColumn::make('description')
                    ->label(__('Description')),
                TextColumn::make('locale')
                    ->label(__('Locale'))
                    ->getStateUsing(function ($record) {
                        return $record->translations->pluck('locale')->join(', ');
                    })
                    ->sortable(),
            ])
            ->filters($this->getTableFilters())
            ->actions(
                $this->getTableActions()
            )
            ->headerActions([
                CreateAction::make('create')
                    ->label(__('Add Survey'))
                    ->form([
                        Grid::make(1)
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('Name'))
                                    ->required(),
                                Textarea::make('description')
                                    ->label(__('Description')),
                            ])
                    ])
                    ->modalSubmitAction()
                    ->using(function ($data, CreateAction $action) {
                        $this->authorize('Survey Create');

                        if (Survey::exists()) {
                            Toaster::warning(__('Survey already exists'));
                            $action->cancel();
                            return null;
                        }

                        $data['created_by'] = auth()->id();
                        return Survey::create($data);
                    })
                    ->after(function (){
                        Toaster::success(__('Survey created successfully'));
                    })
                    ->visible(fn() => auth()->user()->can('Survey Create')),
            ])
            ->bulkActions($this->getTableBulkAction());
    }

    protected function getTableFilters(): array
    {
        return [
            Filter::make('name')
                ->form([
                    TextInput::make('name')
                        ->label(__('Name'))
                        ->placeholder(__('Search by name'))
                ])
                ->query(function (Builder $query, array $data) {
                    if (!empty($data['name'])) {
                        $query->whereTranslationLike('name', '%' . $data['name'] . '%');
                    }
                }),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            InfoAction::make('info')
                ->label('')
                ->visible(fn() => auth()->user()->can('Survey Info')),
            ViewAction::make('view')
                ->form(function (Forms\Form $form, $record) {
                    return $form->schema([
                        Grid::make(1)
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('Name'))
                                    ->default($record->name)
                                    ->disabled(),
                                Textarea::make('description')
                                    ->label(__('Description'))
                                    ->default($record->description)
                                    ->disabled(),
                            ]),
                    ]);
                })
                ->modalSubmitAction(false)
                ->visible(fn() => auth()->user()->can('Survey View')),

            Action::make('questions')
                ->label('')
                ->icon('solar-clipboard-list-bold')
                ->tooltip(__('Questions'))
                ->color('warning')
                ->size('xl')
                ->form(function (Forms\Form $form, $record) {
                    return $form->schema([
                        Repeater::make('questions')
                            ->label(__('Questions'))
                            ->collapsed()
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                            ->schema([
                                Grid::make(3)->schema([
                                    Textarea::make('name')
                                        ->columnSpanFull()
                                        ->label(__('Name'))
                                        ->required(),

                                    Select::make('type')
                                        ->columnSpanFull()
                                        ->options(QuestionType::options())
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(fn (callable $set) => $set('options', null))
                                        ->label(__('Type')),

                                    Repeater::make('options')
                                        ->label(__('Options'))
                                        ->schema([
                                            TextInput::make('value')
                                                ->required()
                                                ->label(__('Option Value')),
                                        ])
                                        ->columnSpanFull()
                                        ->visible(fn (Get $get): bool => $get('type') == QuestionType::RADIO->value || $get('type') == QuestionType::CHECKBOX->value)
                                        ->minItems(2)
                                        ->addActionLabel(__('Add Option'))
                                        ->deleteAction(
                                            fn (FormAction $action) => $action->requiresConfirmation()
                                        )
                                ])
                            ])
                            ->columns(1)
                            ->addActionLabel(__('Add Question'))
                    ]);
                })
                ->mountUsing(function (Form $form, $record) {
                    // يمكنك تحميل الأسئلة الموجودة هنا إذا كانت متاحة
                    $existingQuestions = $record->questions ?? [];

                    $form->fill([
                        'questions' => $existingQuestions
                    ]);
                })
                ->action(function ($data, $record) {
                    $this->authorize('Clinic Survey Question Create');

                    $record->questions()->delete();

                    if (empty($data['questions'])) {
                        return;
                    }

                    foreach ($data['questions'] as $questionData) {

                        if ($questionData['type'] == QuestionType::RADIO->value) {
                            if (empty($questionData['options']) || count($questionData['options']) < 2) {
                                Toaster::error(__('أسئلة الاختيار من متعدد تحتاج على الأقل خيارين'));
                                return;
                            }
                        }

                        $questionData['created_by'] = auth()->id();

                         $record->questions()->create($questionData);
                    }

                    Toaster::success(__('تم إنشاء أو تحديث الأسئلة بنجاح'));
                })
                ->visible(fn() => auth()->user()->can('Clinic Survey Question Create')),

            EditAction::make('edit')
                ->label('')
                ->form(function (Forms\Form $form, $record) {
                    return $form->schema([
                        Grid::make(1)
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('Name'))
                                    ->default($record->name)
                                    ->required(),
                                Textarea::make('description')
                                    ->label(__('Description'))
                                    ->default($record->description),
                            ])
                    ]);
                })
                ->mutateFormDataUsing(function (array $data): array {
                    $data['locale'] = app()->getLocale();
                    return $data;
                })
                ->action(function ($data , $record){
                    $this->authorize('Survey Update');
                    $record->update($data);
                    Toaster::success(__('Survey updated successfully'));
                })
                ->visible(fn() => auth()->user()->can('Survey Update')),

            DeleteAction::make('delete')
                ->action(function ($record) {
                    $this->authorize('Survey Delete');
                    $record->forceDelete();
                    Toaster::success(__('Survey deleted successfully'));
                })
                ->visible(fn() => auth()->user()->can('Survey Delete')),
        ];
    }

    public function getTableBulkAction(): array
    {
        return [
            BulkActionGroup::make([
                BulkAction::make('delete')
                    ->label(__('Delete'))
                    ->requiresConfirmation()
                    ->action(fn(Collection $records) => $records->each->delete())
                    ->visible(fn() => auth()->user()->can('Survey Delete')),
            ])
        ];
    }

    public function render()
    {
        return view('clinic::livewire.pages.survey.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Surveys List'), 'url' => route('surveys.index')],
            ]
        ]);
    }
}
