<?php

namespace Modules\Clinic\Livewire\Pages\Program\TypesOfMeals;

use App\View\Components\AppLayout;
use Filament\Forms\Concerns\InteractsWithForms;
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
use Modules\Clinic\Entities\ProgramTypeOfMeal;
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
            ->query(fn() => ProgramTypeOfMeal::query()->with('createdBy' , 'translations'))
            ->heading(__('Meal Types'))
            ->emptyStateHeading(__('No meal types found'))
            ->emptyStateDescription(__('Create a new meal type by clicking the button below'))
            ->emptyStateActions([
                CreateAction::make()
                    ->label(__('Add Meal Type'))
                    ->visible(fn() => auth()->user()->can('Program Type Of Meal Create'))
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
                    ->label(__('Add Meal Type'))
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
                    ->action(function ($data, $action){
                        $this->authorize('Program Type Of Meal Create');
                        $data['created_by'] = auth()->user()->id;
                        ProgramTypeOfMeal::create($data);
                        Toaster::success(__('Meal type created successfully'));
                        $action->halt();
                        $action->getForm()->fill();
                    })
                    ->visible(fn() => auth()->user()->can('Program Type Of Meal Create')),
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
                ->visible(fn() => auth()->user()->can('Program Type Of Meal Info')),
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
                ->visible(fn() => auth()->user()->can('Program Type Of Meal View')),

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
                    $this->authorize('Program Type Of Meal Update');
                    $record->update($data);
                    Toaster::success(__('Meal type updated successfully'));
                })
                ->visible(fn() => auth()->user()->can('Program Type Of Meal Update')),

            DeleteAction::make('delete')
                ->action(function ($record) {
                    $this->authorize('Program Type Of Meal Delete');
                    $record->delete();
                    Toaster::success(__('Meal type deleted successfully'));
                })
                ->visible(fn() => auth()->user()->can('Program Type Of Meal Delete')),
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
                    ->visible(fn() => auth()->user()->can('Program Type Of Meal Delete')),
            ])
        ];
    }

    public function render()
    {
        return view('clinic::livewire.pages.program.types-of-meals.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Meal Types List'), 'url' => route('program.types-of-meals.index')],
            ]
        ]);
    }
}
