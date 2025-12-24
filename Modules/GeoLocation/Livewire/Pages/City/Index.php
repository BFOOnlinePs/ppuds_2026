<?php

namespace Modules\GeoLocation\Livewire\Pages\City;

use App\View\Components\AppLayout;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Set;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Core\Filament\Forms\Components\CreateAction;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\GeoLocation\Entities\City;
use Modules\GeoLocation\Entities\Country;
use Modules\GeoLocation\Enums\CityType;
use Modules\Items\Entities\Brand;
use Modules\Items\Entities\Category;
use Svg\Tag\Text;

class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable, InteractsWithForms;

    public function table(Table $table)
    {
        return $table
            ->query(fn() => City::query()->with('translations'))
            ->heading(__('City'))
            ->emptyStateHeading(__('No cities found'))
            ->emptyStateDescription(__('Create a new city by clicking the button below'))
            ->emptyStateActions([
                CreateAction::make()
                    ->label(__('Add City'))
                    ->url(route('cities.add'))
                    ->visible(fn() => auth()->user()->can('City Create'))
            ])
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->color('primary')
                    ->icon('solar-pen-new-square-bold')
                    ->url(fn(City $record) => route('cities.edit', $record->id))
                    ->searchable(
                        query: function (Builder $query, string $search): Builder {
                            return $query->whereHas('translations', function ($q) use ($search) {
                                $q->where('name', 'like', '%' . $search . '%');
                            });
                        }
                    ),
                TextColumn::make('governorate.name')
                    ->label(__('Governorate')),
                TextColumn::make('governorate.country.name')
                    ->label(__('Country')),
                TextColumn::make('latitude')
                    ->label(__('Latitude')),
                TextColumn::make('longitude')
                    ->label(__('Longitude')),
                TextColumn::make('population')
                    ->label(__('Population')),
                TextColumn::make('type')
                    ->label(__('Type'))
                    ->formatStateUsing(fn ($state) => $state->label()),
                TextColumn::make('is_capital')
                    ->label(__('Is Capital'))
                    ->formatStateUsing(fn ($state) => $state ? __('Yes') : __('No')),
                TextColumn::make('capital_type')
                    ->label(__('Capital Type'))
                    ->formatStateUsing(fn ($state) => $state->label()),
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
                    ->label(__('Add City'))
                    ->url(route('cities.add'))
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['locale'] = app()->getLocale();
                        $data['created_by'] = auth()->id();
                        return $data;
                    })
                    ->action(function ($form, array $data) {

                        $imageComponent = collect($form->getFlatComponents())
                            ->first(fn($component) => $component->getName() === 'image');

                        $imageFile = $imageComponent?->getState();

                        $brand = Brand::create($data);

                        if (isset($imageFile)) {
                            $brand->addImage($imageFile);
                        }

                        Toaster::success(__('City created successfully'));
                    })
                    ->visible(fn() => auth()->user()->can('City Create'))
            ])
            ->bulkActions($this->getTableBulkAction());
    }

    public function getTableBulkAction(): array
    {
        return [
            BulkActionGroup::make([
                BulkAction::make('delete')
                    ->label(__('Delete'))
                    ->requiresConfirmation()
                    ->action(fn(Collection $records) => $records->each->delete())
                    ->visible(fn() => auth()->user()->can('City Delete')),
            ])
        ];
    }

    protected function getTableActions(): array
    {
        return [
            // ActivityLogAction::make('activity_log')->label(__('Activity Log')),
            InfoAction::make('info')
                ->label('')
                ->visible(fn() => auth()->user()->can('Category Info')),
            ViewAction::make('view')
                ->form(function (Forms\Form $form, $record) {
                    return $form->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('Name'))
                                    ->default($record->name)
                                    ->disabled(),

                                TextInput::make('slug')
                                    ->label(__('Slug'))
                                    ->default($record->slug)
                                    ->disabled()
                            ]),
                        Grid::make(1)
                            ->schema([
                                Textarea::make('description')
                                    ->label(__('Description'))
                                    ->default($record->description)
                                    ->disabled(),
                            ]),
                    ]);
                })
                ->modalSubmitAction(false)
                ->visible(fn() => auth()->user()->can('City View')),
            EditAction::make('edit')
                ->url(fn(City $record) => route('cities.edit', $record->id))
                ->visible(fn() => auth()->user()->can('City Update')),

            DeleteAction::make('delete')
                ->action(fn($record) => $record->forceDelete())
                ->visible(fn() => auth()->user()->can('City Delete')),
        ];
    }


    public function render()
    {
        return view('geolocation::livewire.pages.city.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Cities List'), 'url' => route('cities.index')],
            ]
        ]);
    }
}
