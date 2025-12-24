<?php

namespace Modules\Delivery\Livewire\Pages\DeliveryZone;

use App\View\Components\AppLayout;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
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
use MatanYadaev\EloquentSpatial\Objects\LineString;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Objects\Polygon;
use Modules\Branch\Entities\Branch;
use Modules\Core\Filament\Forms\Components\CreateAction;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\Core\Filament\Forms\Components\MapPicker;
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\Delivery\Entities\DeliveryPricing;
use Modules\Delivery\Entities\DeliveryZone;

class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn() => DeliveryZone::query()->with('createdBy' , 'translations'))
            ->heading(__('Delivery Zone'))
            ->emptyStateHeading(__('No delivery zones found'))
            ->emptyStateDescription(__('Create a new delivery zone by clicking the button below'))
            ->emptyStateActions([
                CreateAction::make()
                    ->label(__('Add Delivery Zone'))
                    ->visible(fn() => auth()->user()->can('Delivery Zone Create'))
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
                    ->label(__('Add Delivery Zone'))
                    ->form([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('Name'))
                                    ->required(),

                                Select::make('branch_id')
                                    ->label(__('Branch'))
                                    ->required()
                                    ->searchable()
                                    ->options(Branch::all()->pluck('name', 'id')),

                                MapPicker::make('zone_area')
                                    ->enableDrawing('zone_area')
                                    ->required()
                                ,

                                Select::make('delivery_pricing_id')
                                    ->label(__('Delivery Pricing'))
                                    ->required()
                                    ->columnSpanFull()
                                    ->searchable()
                                    ->options(DeliveryPricing::all()->pluck('name', 'id')),
                            ])
                    ])
                    ->using(function ($data , $action){
                        $this->authorize('Delivery Zone Create');


                        if (!empty($data['zone_area'])) {
                            $raw = $data['zone_area'];

                            // إن كان نص JSON فككه
                            $decoded = is_string($raw) ? json_decode($raw, true) : $raw;

                            // إذا كانت FeatureCollection استخرج الـ geometry من أول feature
                            if (!empty($decoded['features'][0]['geometry'])) {
                                $geometry = $decoded['features'][0]['geometry'];
                                // خزّن كمصفوفة geometry (أو نص بحسب ما يطلب الموديل)
                                // هنا نحولها إلى نص JSON لأن الموديل سيعالجها في setZoneAreaAttribute أدناه
                                $data['zone_area'] = json_encode($geometry);
                            } else {
                                $data['zone_area'] = null;
                            }
                        }

                        $deliveryZone = DeliveryZone::create($data);
                        return $deliveryZone;
                    })
                    ->after(function (){
                        Toaster::success(__('Delivery Zone created successfully'));
                    })
                    ->visible(fn() => auth()->user()->can('Delivery Zone Create')),
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
                ->visible(fn() => auth()->user()->can('Delivery Zone Info')),
            ViewAction::make('view')
                ->label('')
//                ->fillForm(function ($record) {
//                    return [
//                        'name' => $record->name,
//                        'base_fee' => $record->base_fee,
//                        'price_per_km' => $record->price_per_km,
//                        'description' => $record->description,
//                    ];
//                })
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
                ->visible(fn() => auth()->user()->can('Delivery Zone View')),

            EditAction::make('edit')
                ->label('')
                ->fillForm(function (DeliveryZone $record) {

                    // املأ الحقول العادية
                    $filled = $record->only([
                        'name',
                        'branch_id',
                        'delivery_pricing_id',
                        'description'
                    ]);

                    // 1. تحقق من وجود المنطقة
                    if ($record->zone_area) {

                        // 2. $record->zone_area هو كائن Polygon
                        // قم بتحويله إلى مصفوفة GeoJSON
                        $geoJsonArray = $record->zone_area->toArray();

                        // 3. قم ببناء هيكل FeatureCollection الذي يتوقعه المكون
                        $featureCollection = [
                            'type' => 'FeatureCollection',
                            'features' => [
                                [
                                    'type' => 'Polygon',
                                    'properties' => new \stdClass(),
                                    'geometry' => $geoJsonArray, // هذا هو الـ Polygon
                                ],
                            ],
                        ];

                        // 4. 💡 النقطة الأهم: قم بتخزين الحالة كـ "نص JSON"
                        // هذا يطابق ما يحفظه afterStateUpdated ويحل خطأ Livewire
                        $filled['zone_area'] = json_encode(['geojson' => $featureCollection]);

                    } else {
                        // إذا كانت القيمة فارغة
                        $filled['zone_area'] = null;
                    }

                    return $filled;
                })
                ->form(function (Forms\Form $form, $record) {
                    return $form->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('Name'))
                                    ->required(),

                                Select::make('branch_id')
                                    ->label(__('Branch'))
                                    ->required()
                                    ->searchable()
                                    ->options(Branch::all()->pluck('name', 'id')),

                                MapPicker::make('zone_area')
                                    ->enableDrawing('zone_area')
                                    ->required()
                                ,

                                Select::make('delivery_pricing_id')
                                    ->label(__('Delivery Pricing'))
                                    ->required()
                                    ->columnSpanFull()
                                    ->searchable()
                                    ->options(DeliveryPricing::all()->pluck('name', 'id')),
                            ])
                    ]);
                })
                ->action(function (array $data, DeliveryZone $record){
                    $this->authorize('Delivery Zone Update');


                    if (isset($data['zone_area']['geojson']['features'][0]['geometry'])) {

                        $geometry = $data['zone_area']['geojson']['features'][0]['geometry'];

                        if ($geometry['type'] === 'Polygon') {
                            $data['zone_area'] = $geometry;
                        } else {
                            throw \Illuminate\Validation\ValidationException::withMessages([
                                'zone_area' => __('The delivery zone must be a closed area (Polygon).'),
                            ]);
                        }
                    } elseif (array_key_exists('zone_area', $data)) {
                        // هذا يعني أن المستخدم ربما مسح الخريطة
                        $data['zone_area'] = null;
                    }

                    $record->update($data);
                    Toaster::success(__('Delivery Zone updated successfully'));
                })
                ->visible(fn() => auth()->user()->can('Delivery Zone Update')),

            DeleteAction::make('delete')
                ->action(function ($record) {
                    $this->authorize('Delivery Zone Delete');
                    $record->delete();
                    Toaster::success(__('Delivery zone deleted successfully')); // مترجمة
                })
                ->visible(fn() => auth()->user()->can('Delivery Zone Delete')),
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
                    ->visible(fn() => auth()->user()->can('Delivery Zone Delete')),
            ])
        ];
    }

    public function render()
    {
        return view('delivery::livewire.pages.delivery-zone.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Delivery Zone List'), 'url' => route('delivery-zone.index')],
            ]
        ]);
    }
}
