<?php

namespace Modules\GeoLocation\Livewire\Pages\Governorate;

use App\View\Components\AppLayout;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
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
use Filament\Tables\Filters\Filter;
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
use Modules\GeoLocation\Entities\Country;
use Modules\GeoLocation\Entities\Governorate;
use Modules\Items\Entities\Brand;
use Modules\Items\Entities\Category;

class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable, InteractsWithForms;

    public function table(Table $table)
    {
        return $table
            ->query(fn() => Governorate::query()->with('translations'))
            ->heading(__('Governorate'))
            ->emptyStateHeading(__('No governorates found'))
            ->emptyStateDescription(__('Create a new governorate by clicking the button below'))
            ->emptyStateActions([
                CreateAction::make()
                    ->label(__('Add Governorate'))
                    ->url(route('governorates.add'))
                    ->visible(fn() => auth()->user()->can('Governorate Create'))
            ])
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->color('primary')
                    ->icon('solar-pen-new-square-bold')
                    ->url(fn(Governorate $record) => route('governorates.edit', $record->id))
                    ->searchable(
                        query: function (Builder $query, string $search): Builder {
                            return $query->whereHas('translations', function ($q) use ($search) {
                                $q->where('name', 'like', '%' . $search . '%');
                            });
                        }
                    ),
                TextColumn::make('code')
                    ->label(__('Code'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('country.name')
                    ->label(__('Country')),
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
                    ->label(__('Add Governorate'))
                    ->url(route('governorates.add'))
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

                        Toaster::success(__('Governorate created successfully'));
                    })
                    ->visible(fn() => auth()->user()->can('Governorate Create'))
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
            Filter::make('code')
                ->form([
                    Select::make('code')
                        ->label(__('Code'))
                        ->options(Governorate::all()->pluck('code', 'code')->toArray())
                        ->searchable()
                ])
                ->query(function (Builder $query, array $data) {
                    if (!empty($data['code'])) {
                        $query->where('code', $data['code']);
                    }
                }),
            Filter::make('locale')
                ->form([
                    Select::make('locale')
                        ->label(__('Locale'))
                        ->options(collect(\LaravelLocalization::getSupportedLocales())
                            ->mapWithKeys(fn($properties, $key) => [$key => $properties['native']]))
                        ->searchable()
                ])
                ->query(function (Builder $query, array $data) {
                    if (!empty($data['locale'])) {
                        $query->whereHas('translations', function ($q) use ($data) {
                            $q->where('locale', $data['locale']);
                        });
                    }
                })
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
                    ->visible(fn() => auth()->user()->can('Governorate Delete')),
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
                ->visible(fn() => auth()->user()->can('Governorate View')),
            EditAction::make('edit')
                ->url(fn(Governorate $record) => route('governorates.edit', $record->id))
                ->visible(fn() => auth()->user()->can('Governorate Update')),

            DeleteAction::make('delete')
                ->action(fn($record) => $record->forceDelete())
                ->visible(fn() => auth()->user()->can('Governorate Delete')),
        ];
    }


    public function render()
    {
        return view('geolocation::livewire.pages.governorate.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Governorates List'), 'url' => route('governorates.index')],
            ]
        ]);
    }
}
