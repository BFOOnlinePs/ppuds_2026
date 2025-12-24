<?php

namespace Modules\Delivery\Livewire\Pages\DeliveryPricing;

use App\View\Components\AppLayout;
use Astrotomic\Translatable\Validation\Rules\TranslatableUnique;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Get;
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
use Modules\Branch\Enums\BranchStatus;
use Modules\Branch\Entities\Branch;
use Modules\Core\Filament\Forms\Components\CreateAction;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Filament\Forms\Components\Toggle;
use Modules\Delivery\Entities\DeliveryPricing;
use Modules\GeoLocation\Entities\City;
use Modules\GeoLocation\Entities\Country;
use Modules\Marketing\Entities\LoyaltyRule;
use Modules\Marketing\Enums\LoyaltyRuleType;
use Nwidart\Modules\Facades\Module;

class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn() => DeliveryPricing::query()->with('createdBy' , 'translations', 'deliveryFeeTiers'))
            ->heading(__('Delivery Pricing'))
            ->emptyStateHeading(__('No delivery pricing found'))
            ->emptyStateDescription(__('Create a new delivery pricing by clicking the button below'))
            ->emptyStateActions([
                CreateAction::make()
                    ->label(__('Add Delivery Pricing'))
                    ->visible(fn() => auth()->user()->can('Delivery Pricing Create'))
            ])
            ->columns([
                TextColumn::make('name')
                    ->searchable(
                        query:function (Builder $query, string $search): Builder {
                            return $query->whereTranslationLike('name', '%' . $search . '%');
                        }
                    )
                    ->label(__('Name')),

                TextColumn::make('base_fee')
                    ->label(__('Base fee')),

                TextColumn::make('price_per_km')
                    ->label(__('Price per km')),

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
                    ->label(__('Add Delivery Pricing'))
                    ->form([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('Name'))
                                    ->required()
                                    ->columnSpanFull(),

                                TextInput::make('base_fee')
                                    ->label(__('Base fee'))
                                    ->required()
                                    ->numeric(),

                                TextInput::make('price_per_km')
                                    ->label(__('Price per km'))
                                    ->required()
                                    ->numeric(),

                                Section::make('Delivery Fee Tiers')
                                    ->schema([
                                        Repeater::make('deliveryFeeTiers')
                                        ->relationship()
                                        ->schema([
                                            TextInput::make('min_distance_km')
                                            ->label(__('Min distance km'))
                                                ->numeric()
                                                ->required(),
                                            TextInput::make('extra_charge')
                                            ->label(__('Extra charge'))
                                                ->numeric()
                                                ->required(),
                                        ])
                                            ->columns(2)
                                            ->helperText('أضف سعراً إضافياً عند تجاوز مسافة معينة.'),
                                    ])->columnSpanFull(),

                                Textarea::make('description')
                                    ->label(__('Description'))
                                    ->columnSpanFull()
                            ])
                    ])
                    ->using(function ($data , $action){
                        $this->authorize('Delivery Pricing Create');
                        $data['created_by'] = auth()->id();
                        $deliveryPricing = DeliveryPricing::create($data);
                        return $deliveryPricing;
                    })
                    ->after(function (){
                        Toaster::success(__('Delivery Pricing created successfully'));
                    })
                    ->visible(fn() => auth()->user()->can('Delivery Pricing Create')),
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
                ->visible(fn() => auth()->user()->can('Delivery Pricing Info')),
            ViewAction::make('view')
                ->label('')
                ->fillForm(function ($record) {
                    return [
                        'name' => $record->name,
                        'base_fee' => $record->base_fee,
                        'price_per_km' => $record->price_per_km,
                        'description' => $record->description,
                        'deliveryFeeTiers' => $record->deliveryFeeTiers->map(function ($tier) {
                            return [
                                'id' => $tier->id,
                                'min_distance_km' => $tier->min_distance_km,
                                'extra_charge' => $tier->extra_charge,
                            ];
                        })->toArray(),
                    ];
                })
                ->form(function (Forms\Form $form, $record) {
                    return $form->schema([
                        Grid::make(2)
                        ->schema([
                            TextInput::make('name')
                                ->label(__('Name'))
                                ->required()
                                ->columnSpanFull(),

                            TextInput::make('base_fee')
                                ->label(__('Base fee'))
                                ->required()
                                ->numeric(),

                            TextInput::make('price_per_km')
                                ->label(__('Price per km'))
                                ->required()
                                ->numeric(),

                            Section::make('Delivery Fee Tiers')
                                ->schema([
                                    Repeater::make('deliveryFeeTiers')
                                        ->relationship()
                                        ->schema([
                                            TextInput::make('min_distance_km')
                                                ->label(__('Min distance km'))
                                                ->numeric()
                                                ->required(),
                                            TextInput::make('extra_charge')
                                                ->label(__('Extra charge'))
                                                ->numeric()
                                                ->required(),
                                        ])
                                        ->columns(2)
                                        ->helperText('أضف سعراً إضافياً عند تجاوز مسافة معينة.'),
                                ])->columnSpanFull(),

                            Textarea::make('description')
                                ->label(__('Description'))
                                ->columnSpanFull()
                        ]),
                    ])->disabled();
                })
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('Close'))
                ->visible(fn() => auth()->user()->can('Delivery Pricing View')),

            EditAction::make('edit')
                ->label('')
                ->fillForm(function ($record) {
                    return [
                        'name' => $record->name,
                        'base_fee' => $record->base_fee,
                        'price_per_km' => $record->price_per_km,
                        'description' => $record->description,
                        'deliveryFeeTiers' => $record->deliveryFeeTiers->map(function ($tier) {
                            return [
                                'id' => $tier->id,
                                'min_distance_km' => $tier->min_distance_km,
                                'extra_charge' => $tier->extra_charge,
                            ];
                        })->toArray(),
                    ];
                })
                ->form(function (Forms\Form $form, $record) {
                    return $form->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('Name'))
                                    ->required()
                                    ->columnSpanFull(),

                                TextInput::make('base_fee')
                                    ->label(__('Base fee'))
                                    ->required()
                                    ->numeric(),

                                TextInput::make('price_per_km')
                                    ->label(__('Price per km'))
                                    ->required()
                                    ->numeric(),

                                Section::make('Delivery Fee Tiers')
                                    ->schema([
                                        Repeater::make('deliveryFeeTiers')
                                            ->relationship()
                                            ->schema([
                                                TextInput::make('min_distance_km')
                                                    ->label(__('Min distance km'))
                                                    ->numeric()
                                                    ->required(),
                                                TextInput::make('extra_charge')
                                                    ->label(__('Extra charge'))
                                                    ->numeric()
                                                    ->required(),
                                            ])
                                            ->columns(2)
                                            ->helperText('أضف سعراً إضافياً عند تجاوز مسافة معينة.'),
                                    ])->columnSpanFull(),

                                Textarea::make('description')
                                    ->label(__('Description'))
                                    ->columnSpanFull()
                            ])
                    ]);
                })
                ->action(function (array $data, DeliveryPricing $record){
                    $this->authorize('Delivery Pricing Update');
                    $record->update($data);
                    Toaster::success(__('Delivery Pricing updated successfully'));
                })
                ->visible(fn() => auth()->user()->can('Delivery Pricing Update')),

            DeleteAction::make('delete')
                ->action(function ($record) {
                    $this->authorize('Delivery Pricing Delete');
                    $record->delete();
                    Toaster::success(__('delivery Pricing deleted successfully')); // مترجمة
                })
                ->visible(fn() => auth()->user()->can('Delivery Pricing Delete')),
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
                    ->visible(fn() => auth()->user()->can('Delivery Pricing Delete')),
            ])
        ];
    }

    public function render()
    {
        return view('delivery::livewire.pages.delivery-pricing.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Delivery Pricing List'), 'url' => route('delivery-pricing.index')],
            ]
        ]);
    }
}
