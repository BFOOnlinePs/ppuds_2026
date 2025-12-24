<?php

namespace Modules\Clinic\Livewire\Pages\Food\Item;

use App\View\Components\AppLayout;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;
use Filament\Tables\Actions\Action;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;
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
use Modules\Clinic\Entities\FoodItem;
use Modules\Clinic\Entities\ServingSize;
use Modules\Core\Filament\Forms\Components\CreateAction;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Filament\Forms\Components\Toggle;
use Modules\Core\Rules\UniqueTranslation;
use Astrotomic\Translatable\Validation\Rules\TranslatableUnique;

class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table)
    {
        return $table
            ->query(fn() => FoodItem::query()->with('createdBy' , 'translations'))
            ->heading(__('Food Items'))
            ->emptyStateHeading(__('No food items found'))
            ->emptyStateDescription(__('Create a new food item by clicking the button below')) // مترجمة
            ->emptyStateActions([
                CreateAction::make()
                    ->label(__('Add Food Item'))
                    ->visible(fn() => auth()->user()->can('Food Item Create'))
            ])
            ->columns([
                TextColumn::make('name')
                    ->searchable(
                        query:function (Builder $query, string $search): Builder {
                            return $query->whereTranslationLike('name', '%' . $search . '%');
                        }
                    )
                    ->label(__('Name')),
                TextColumn::make('foodCategory.name')
                    ->label(__('Food Category')),
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
                    ->label(__('Add Food Item'))
                    ->form([
                        Grid::make(1)
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('name')
                                            ->columnSpan('1')
                                            ->label(__('Name'))
                                            ->rules([
                                                new TranslatableUnique(FoodItem::class, 'name')
                                            ])
                                            ->required(),
                                        Select::make('food_category_id')
                                            ->label(__('Food Category'))
                                            ->required()
                                            ->searchable()
                                            ->createOptionForm([
                                                Grid::make(1)
                                                    ->schema([
                                                        TextInput::make('name')
                                                            ->label(__('Name'))
                                                            ->required()
                                                            ->rules([
                                                                new TranslatableUnique(FoodCategory::class, 'name')
                                                            ])
                                                            ->validationMessages([
                                                                'unique' => __('The :attribute has already been taken.')
                                                            ]),
                                                        Textarea::make('description')
                                                            ->label(__('Description')),
                                                    ])
                                            ])
                                            ->createOptionUsing(function (array $data): int {
                                                $this->authorize('Food Category Create');
                                                $data['created_by'] = auth()->id();
                                                $foodCategory = FoodCategory::create($data);
                                                Toaster::success(__('Food category created successfully'));

                                                return $foodCategory->id;
                                            })
                                            ->options(FoodCategory::all()->pluck('name', 'id')),
                                        Textarea::make('description')
                                            ->columnSpanFull()
                                            ->label(__('Description')),
                                    ])
                            ])
                    ])
                    ->using(function ($data, CreateAction $action) {
                        $this->authorize('Food Item Create');
                        $data['created_by'] = auth()->id();
                        return FoodItem::create($data);


                    })
                    ->after(function (){
                        Toaster::success(__('Food item created successfully'));
                    })
                    ->visible(fn() => auth()->user()->can('Food Item Create')),
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
                        ->placeholder(__('Search...'))
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
                ->visible(fn() => auth()->user()->can('Food Item Info')),
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
                ->visible(fn() => auth()->user()->can('Food Item View')),

            Action::make('serving_sizes')
                ->label('')
                ->icon('solar-jar-of-pills-bold-duotone')
                ->color('warning')
                ->size('xl')
                ->form(function (Forms\Form $form, $record) {
                    return $form->schema([
                        Repeater::make('serving_sizes')
                            ->label(__('Serving Sizes'))
                            ->collapsed()
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('name')
                                        ->columnSpanFull()
                                        ->required()
                                        ->label(__('Name')),
                                    TextInput::make('gram')
                                        ->label(__('Gram'))
                                        ->required()
                                        ->numeric(),
                                    TextInput::make('calories')
                                        ->label(__('Calories'))
                                        ->numeric(),
                                    TextInput::make('protein')
                                        ->label(__('Protein'))
                                        ->numeric(),
                                    TextInput::make('carbohydrate')
                                        ->label(__('Carbohydrate'))
                                        ->numeric(),
                                    TextInput::make('fat')
                                        ->label(__('Fat'))
                                        ->numeric(),
                                    TextInput::make('fiber')
                                        ->label(__('Fiber'))
                                        ->numeric(),
                                    Textarea::make('description')
                                        ->columnSpanFull()
                                        ->label(__('Description')),
                                ])
                            ])
                            ->grid(2)
                            ->columns(1)
                    ]);
                })
                ->mountUsing(function (Form $form, $record){
                    $data = $record->servingSizes->map(function (ServingSize $servingSize) {
                        $itemData = $servingSize->getAttributes();

                        $itemData['name'] = $servingSize->name;
                        $itemData['description'] = $servingSize->description;

                        return $itemData;
                    })->toArray();

                    $form->fill([
                        'serving_sizes' => $record->servingSizes()->get()
                    ]);
                })
                ->action(function ($data , $record) {
//                    $this->authorize('Serving Sizes Create');

                    $record->servingSizes()->delete();

                    if (empty($data['serving_sizes'])) {
                        return;
                    }

                    foreach ($data['serving_sizes'] as $servingSizeData) {
                        $servingSizeData['created_by'] = auth()->id();

                        $record->servingSizes()->create($servingSizeData);
                    }

                    Toaster::success(__('Serving sizes created or updated successfully'));
                }),

            EditAction::make('edit')
                ->label('')
                ->form(function (Forms\Form $form, $record) {
                    return $form->schema([
                        Grid::make(1)
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('name')
                                            ->columnSpan('1')
                                            ->label(__('Name'))
                                            ->rules([
                                                new TranslatableUnique(FoodItem::class, 'name')
                                            ])
                                            ->default($record->name)
                                            ->required(),
                                        Select::make('food_category_id')
                                            ->label(__('Food Category'))
                                            ->default($record->food_category_id)
                                            ->searchable()
                                            ->options(FoodCategory::all()->pluck('name', 'id')),
                                        Textarea::make('description')
                                            ->columnSpanFull()
                                            ->default($record->description)
                                            ->label(__('Description')),
                                    ])
                            ])
                    ]);
                })
                ->mutateFormDataUsing(function (array $data): array {
                    $data['locale'] = app()->getLocale();
                    return $data;
                })
                ->action(function ($data , $record){
                    $this->authorize('Food Item Update');
                    $record->update($data);
                    Toaster::success(__('food item updated successfully')); // مترجمة
                })
                ->visible(fn() => auth()->user()->can('Food Item Update')),

            DeleteAction::make('delete')
                ->action(function ($record) {
                    $this->authorize('Food Item Delete');
                    $record->delete();
                    Toaster::success(__('food item deleted successfully')); // مترجمة
                })
                ->visible(fn() => auth()->user()->can('Food Item Delete')),
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
                    ->visible(fn() => auth()->user()->can('Food Item Delete')),
            ])
        ];
    }

    public function render()
    {
        return view('clinic::livewire.pages.food.item.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Food Item List'), 'url' => route('food.items.index')],
            ]
        ]);
    }
}
