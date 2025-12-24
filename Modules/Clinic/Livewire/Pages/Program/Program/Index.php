<?php

namespace Modules\Clinic\Livewire\Pages\Program\Program;

use App\View\Components\AppLayout;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Actions\Action;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
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
use Modules\Clinic\Entities\MealType;
use Modules\Clinic\Entities\Program;
use Modules\Clinic\Entities\ProgramCategory;
use Modules\Clinic\Entities\ProgramInstruction;
use Modules\Clinic\Entities\ProgramTypeOfMeal;
use Modules\Core\Filament\Forms\Components\CreateAction;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Filament\Forms\Components\Toggle;
use Nwidart\Modules\Facades\Module;

class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table)
    {
        return $table
            ->query(fn() => Program::query()->with('createdBy' , 'translations'))
            ->heading(__('Programs'))
            ->emptyStateHeading(__('No programs found'))
            ->emptyStateDescription(__('Create a new program by clicking the button below'))
            ->emptyStateActions([
                CreateAction::make()
                    ->label(__('Add Program'))
                    ->visible(fn() => auth()->user()->can('Program Create'))
            ])
            ->columns([
                TextColumn::make('name')
                    ->searchable(
                        query:function (Builder $query, string $search): Builder {
                            return $query->whereTranslationLike('name', '%' . $search . '%');
                        }
                    )
                    ->label(__('Name')),
                TextColumn::make('category.name')
                    ->label(__('Category')),
                TextColumn::make('instruction.name')
                    ->label(__('Instruction')),
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
                    ->label(__('Add Program'))
                    ->form([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->columnSpanFull()
                                    ->label(__('Name'))
                                    ->required(),
                                Select::make('category_id')
                                    ->columnSpan(1)
                                    ->label(__('Category'))
                                    ->searchable()
                                    ->required()
                                    ->options(ProgramCategory::all()->pluck('name', 'id')),
                                Select::make('instruction_id')
                                    ->columnSpan(1)
                                    ->label(__('Instruction'))
                                    ->searchable()
                                    ->required()
                                    ->options(ProgramInstruction::all()->pluck('name', 'id')),
                                Textarea::make('description')
                                    ->columnSpanFull()
                                    ->label(__('Description')),
                            ])
                    ])
                    ->action(function ($data, $action){
                        $this->authorize('Program Create');
                        $data['created_by'] = auth()->user()->id;
                        Program::create($data);
                        Toaster::success(__('Program created successfully'));
                        $action->halt();
                        $action->getForm()->fill();
                    })
                    ->visible(fn() => auth()->user()->can('Program Create')),
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
                ->visible(fn() => auth()->user()->can('Program Info')),
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
                ->visible(fn() => auth()->user()->can('Program View')),

            Action::make('details')
                ->label('')
                ->size('xl')
                ->color('warning')
                ->icon('solar-map-point-search-bold')
                ->url(fn($record) => route('program.details.index', $record))
                ->visible(fn() => Module::isEnabled('subscription')),


            EditAction::make('edit')
                ->label('')
                ->form(function (Forms\Form $form, $record) {
                    return $form->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->columnSpanFull()
                                    ->label(__('Name'))
                                    ->default($record->name)
                                    ->required(),
                                Select::make('category_id')
                                    ->columnSpan(1)
                                    ->label(__('Category'))
                                    ->searchable()
                                    ->default($record->category_id)
                                    ->required()
                                    ->options(ProgramCategory::all()->pluck('name', 'id')),
                                Select::make('instruction_id')
                                    ->columnSpan(1)
                                    ->label(__('Instruction'))
                                    ->searchable()
                                    ->default($record->instruction_id)
                                    ->required()
                                    ->options(ProgramInstruction::all()->pluck('name', 'id')),
                                Textarea::make('description')
                                    ->columnSpanFull()
                                    ->default($record->description)
                                    ->label(__('Description')),
                            ])
                    ]);
                })
                ->mutateFormDataUsing(function (array $data): array {
                    $data['locale'] = app()->getLocale();
                    return $data;
                })
                ->action(function ($data , $record){
                    $this->authorize('Program Update');
                    $record->update($data);
                    Toaster::success(__('Program updated successfully'));
                })
                ->visible(fn() => auth()->user()->can('Program Update')),

            DeleteAction::make('delete')
                ->action(function ($record) {
                    $this->authorize('Program Delete');
                    $record->delete();
                    Toaster::success(__('Program deleted successfully'));
                })
                ->visible(fn() => auth()->user()->can('Program Delete')),
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
                    ->visible(fn() => auth()->user()->can('Program Delete')),
            ])
        ];
    }

    public function render()
    {
        return view('clinic::livewire.pages.program.program.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Programs List'), 'url' => route('program.programs.index')],
            ]
        ]);
    }
}
