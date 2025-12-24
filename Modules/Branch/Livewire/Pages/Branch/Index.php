<?php

namespace Modules\Branch\Livewire\Pages\Branch;

use App\View\Components\AppLayout;
use Astrotomic\Translatable\Validation\Rules\TranslatableUnique;
use Cheesegrits\FilamentGoogleMaps\Fields\Map;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Http;
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
use Modules\Branch\Enums\BranchStatus;
use Modules\Branch\Entities\Branch;
use Modules\Core\Filament\Forms\Components\CreateAction;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\Core\Filament\Forms\Components\MapPicker;
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Filament\Forms\Components\Toggle;
use Modules\GeoLocation\Entities\City;
use Modules\GeoLocation\Entities\Country;

class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn() => Branch::query()->with('createdBy' , 'translations'))
            ->heading(__('Branches'))
            ->emptyStateHeading(__('No branches found'))
            ->emptyStateDescription(__('Create a new branch by clicking the button below'))
            ->emptyStateActions([
                CreateAction::make()
                    ->label(__('Add Branch'))
                    ->visible(fn() => auth()->user()->can('Branch Create'))
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
                TextColumn::make('address')
                    ->label(__('Address')),
                TextColumn::make('description')
                    ->label(__('Description')),
                TextColumn::make('phone')
                    ->label(__('Phone')),
                TextColumn::make('country.name')
                    ->label(__('Country')),
                TextColumn::make('city.name')
                    ->label(__('City')),
                TextColumn::make('opening_time')
                    ->label(__('Opening Time')),
                TextColumn::make('closing_time')
                    ->label(__('Closing Time')),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge(BranchStatus::class),
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
                    ->label(__('Add Branch'))
                    ->form([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->columnSpanFull()
                                    ->label(__('Name'))
                                    ->required(),
                                TextInput::make('email')
                                    ->columnSpan(1)
                                    ->email()
                                    ->label(__('Email')),
                                TextInput::make('phone')
                                    ->columnSpan(1)
                                    ->numeric()
                                    ->label(__('Phone')),

                                MapPicker::make('location')
                                    ,
                                TimePicker::make('opening_time')
                                    ->columnSpan(1)
                                    ->default('08:00')
                                    ->label(__('Opening Time')),
                                TimePicker::make('closing_time')
                                    ->columnSpan(1)
                                    ->default('17:00')
                                    ->label(__('Closing Time')),
                                Select::make('country_id')
                                    ->columnSpan(1)
                                    ->label(__('Country'))
                                    ->searchable()
                                    ->options(Country::get()->pluck('name', 'id'))
                                    ->required(),
                                Select::make('city_id')
                                    ->columnSpan(1)
                                    ->label(__('City'))
                                    ->searchable()
                                    ->options(City::get()->pluck('name', 'id'))
                                    ->required(),
                                Textarea::make('address')
                                    ->columnSpanFull()
                                    ->label(__('Address')),
                                Textarea::make('description')
                                    ->columnSpanFull()
                                    ->label(__('Description')),
                            ])
                    ])
                    ->using(function ($data , $action){
                        $this->authorize('Branch Create');
                        $data['created_by'] = auth()->id();
                        $data['latitude'] = $data['location']['lat'];
                        $data['longitude'] = $data['location']['lng'];
                        $branch = Branch::create($data);
                        return $branch;
                    })
                    ->after(function (){
                        Toaster::success(__('Branch created successfully'));
                    })
                    ->visible(fn() => auth()->user()->can('Branch Create')),
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
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->columnSpanFull()
                                    ->label(__('Name'))
                                    ->default($record->name)
                                    ->required(),
                                TextInput::make('email')
                                    ->columnSpan(1)
                                    ->email()
                                    ->label(__('Email'))
                                    ->default($record->email),
                                TextInput::make('phone')
                                    ->columnSpan(1)
                                    ->numeric()
                                    ->label(__('Phone'))
                                    ->default($record->phone),
                                MapPicker::make('location')
                                    ->default(['lat' => $record->latitude, 'lng' => $record->longitude]),
                                TimePicker::make('opening_time')
                                    ->columnSpan(1)
                                    ->default('08:00')
                                    ->label(__('Opening Time'))
                                    ->default($record->opening_time),
                                TimePicker::make('closing_time')
                                    ->columnSpan(1)
                                    ->default('17:00')
                                    ->label(__('Closing Time'))
                                    ->default($record->closing_time),
                                Select::make('country_id')
                                    ->columnSpan(1)
                                    ->label(__('Country'))
                                    ->searchable()
                                    ->options(Country::get()->pluck('name', 'id'))
                                    ->required()
                                    ->default($record->country_id),
                                Select::make('city_id')
                                    ->columnSpan(1)
                                    ->label(__('City'))
                                    ->searchable()
                                    ->options(City::get()->pluck('name', 'id'))
                                    ->required()
                                    ->default($record->city_id),
                                Textarea::make('address')
                                    ->columnSpanFull()
                                    ->label(__('Address'))
                                    ->default($record->address),
                                Textarea::make('description')
                                    ->columnSpanFull()
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
                    $data['latitude'] = $data['location']['lat'];
                    $data['longitude'] = $data['location']['lng'];
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
        return view('branch::livewire.pages.branch.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')], // مترجمة
                ['title' => __('Branches List'), 'url' => route('branches.index')], // مترجمة
            ]
        ]);
    }
}
