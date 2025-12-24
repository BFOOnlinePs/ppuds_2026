<?php

namespace Modules\Clinic\Livewire\Pages\Food\Category;

use App\View\Components\AppLayout;
use Astrotomic\Translatable\Validation\Rules\TranslatableUnique;
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
use Modules\Clinic\Entities\FoodCategory;
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
            ->query(fn() => FoodCategory::query()->with('createdBy' , 'translations'))
            ->heading(__('Food Categories')) // مترجمة
            ->emptyStateHeading(__('No food categories found')) // مترجمة
            ->emptyStateDescription(__('Create a new food category by clicking the button below')) // مترجمة
            ->emptyStateActions([
                CreateAction::make()
                    ->label(__('Add Food Category'))
                    ->visible(fn() => auth()->user()->can('Food Category Create'))
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
                    ->label(__('Add Food Category'))
                    ->form([
                        Grid::make(1)
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('Name'))
                                    ->rules([
                                        new TranslatableUnique(FoodCategory::class, 'name')
                                    ])
                                    ->required(),
                                Textarea::make('description')
                                    ->label(__('Description')),
                            ])
                    ])
                    ->using(function ($data , $action){
                        $this->authorize('Food Category Create');
                        $data['created_by'] = auth()->id();
                        $foodCategory = FoodCategory::create($data);
                        return $foodCategory;
                    })
                    ->after(function (){
                        Toaster::success(__('Food category created successfully'));
                    })
                    ->visible(fn() => auth()->user()->can('Food Category Create')),
            ])
            ->bulkActions($this->getTableBulkAction());
    }

    protected function getTableFilters(): array
    {
        return [
            Filter::make('name')
                ->form([
                    TextInput::make('name')
                        ->label(__('Name')) // مترجمة
                        ->placeholder(__('Search...')) // مترجمة
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
                ->visible(fn() => auth()->user()->can('Food Category Info')),
            ViewAction::make('view')
                ->form(function (Forms\Form $form, $record) {
                    return $form->schema([
                        Grid::make(1)
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('Name')) // مترجمة
                                    ->default($record->name)
                                    ->disabled(),
                                Textarea::make('description')
                                    ->label(__('Description')) // مترجمة
                                    ->default($record->description)
                                    ->disabled(),
                            ]),
                    ]);
                })
                ->modalSubmitAction(false)
                ->visible(fn() => auth()->user()->can('Food Category View')),

            EditAction::make('edit')
                ->label('')
                ->form(function (Forms\Form $form, $record) {
                    return $form->schema([
                        Grid::make(1)
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('Name'))
                                    ->rules([
                                        new TranslatableUnique(FoodCategory::class, 'name')
                                    ])
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
                    $this->authorize('Food Category Update');
                    $record->update($data);
                    Toaster::success(__('food category updated successfully')); // مترجمة
                })
                ->visible(fn() => auth()->user()->can('Food Category Update')),

            DeleteAction::make('delete')
                ->action(function ($record) {
                    $this->authorize('Food Category Delete');
                    $record->delete();
                    Toaster::success(__('food category deleted successfully')); // مترجمة
                })
                ->visible(fn() => auth()->user()->can('Food Category Delete')),
        ];
    }

    public function getTableBulkAction(): array
    {
        return [
            BulkActionGroup::make([
                BulkAction::make('delete')
                    ->label(__('Delete')) // مترجمة
                    ->requiresConfirmation()
                    ->action(fn(Collection $records) => $records->each->delete())
                    ->visible(fn() => auth()->user()->can('Food Category Delete')),
            ])
        ];
    }

    public function render()
    {
        return view('clinic::livewire.pages.food.category.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')], // مترجمة
                ['title' => __('Food Categories List'), 'url' => route('food.categories.index')], // مترجمة
            ]
        ]);
    }
}
