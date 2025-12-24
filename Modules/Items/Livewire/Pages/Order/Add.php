<?php

namespace Modules\Items\Livewire\Pages\Order;

use App\View\Components\AppLayout;
use Dotswan\MapPicker\Fields\Map;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Branch\Entities\Branch;
use Modules\Core\Entities\User;
use Modules\GeoLocation\Entities\City;
use Modules\GeoLocation\Entities\District;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Enums\PaymentMethod;
use Modules\Items\Enums\PaymentStatus;

class Add extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->model(Order::class)
            ->schema([
                Grid::make(3)
                    ->schema([
                        Section::make(__('Order Details'))
                            ->columnSpan(2)
                            ->schema([
                                Grid::make(2)
                                    ->schema([


                                        Select::make('user_id')
                                            ->label(__('Customer'))
                                            ->columnSpan(1)
                                            ->options(User::all()->pluck('name', 'id'))
                                            ->searchable()
                                            ->required(),

                                        Select::make('branch_id')
                                            ->label(__('Branch'))
                                            ->columnSpan(1)
                                            ->options(Branch::all()->pluck('name', 'id'))
                                            ->searchable()
                                            ->required(),

                                        Repeater::make('items')
                                            ->label(__('Products'))
                                            ->columnSpanFull()
                                            ->schema([
                                                Select::make('product_id')
                                                    ->label(__('Product'))
                                                    ->options(Product::all()->pluck('name', 'id'))
                                                    ->searchable()
                                                    ->required()
                                                    ->live()
                                                    // **** تم تصحيح هذا الجزء ****
                                                    ->afterStateUpdated(function ($state, Set $set, Component $livewire) {
                                                        $item = Product::find($state);
                                                        $set('price', $item?->base_price ?? 0);
                                                        // نمرر كامل مصفوفة الداتا
                                                        self::updateTotals($set, $livewire->data);
                                                    }),
                                                TextInput::make('quantity')
                                                    ->label(__('Quantity'))
                                                    ->numeric()
                                                    ->required()
                                                    ->default(1)
                                                    ->live(debounce: 500)
                                                    // **** تم تصحيح هذا الجزء ****
                                                    ->afterStateUpdated(fn(Set $set, Component $livewire) => self::updateTotals($set, $livewire->data)),
                                                TextInput::make('price')
                                                    ->label(__('Price per unit'))
                                                    ->numeric()
                                                    ->required()
                                                    ->readOnly(),
                                            ])
                                            ->addActionLabel(__('Add Product'))
                                            ->columns(3),

                                        // Totals Section
                                        Section::make('')
                                            ->schema([
                                                TextInput::make('sub_total')
                                                    ->label(__('Subtotal'))
                                                    ->live()
                                                    ->numeric()->readOnly()->default(0),
                                                TextInput::make('delivery_fee')
                                                    ->label(__('Delivery Fee'))
                                                    ->numeric()->default(0)
                                                    ->live(debounce: 500)
                                                    // **** تم تصحيح هذا الجزء ****
                                                    ->afterStateUpdated(fn(Set $set, Component $livewire) => self::updateTotals($set, $livewire->data)),                                        TextInput::make('grand_total')
                                                    ->label(__('Grand Total'))
                                                    ->numeric()->readOnly()->default(0),
                                            ])->columns(3),


                                    ])

//                                Map::make('location')
//                                    ->label('Location')
//                                    ->columnSpanFull()
//                                    ->liveLocation()
//                                    ->showMarker()
//                                    ->markerColor("#22c55eff")
//                                    ->showFullscreenControl()
//                                    ->showZoomControl()
//                                    ->draggable()
//                                    ->tilesUrl("http://tile.openstreetmap.de")
//                                    ->zoom(15)
//                                    ->extraTileControl([])
//                                    ->extraControl([
//                                        'zoomDelta'           => 1,
//                                        'zoomSnap'            => 2,
//                                    ]),



                            ]),

                        // ... القسم الجانبي (لم يتغير)
                        Section::make(__('Order Information'))
                            ->columnSpan(1)
                            ->schema([
                                Select::make('status')
                                    ->label(__('Order Status'))
                                    ->options(OrderStatus::class)
                                    ->required()
                                    ->default(OrderStatus::PENDING),
                                Select::make('payment_method')
                                    ->label(__('Payment Method'))
                                    ->options(PaymentMethod::class)
                                    ->required()
                                    ->default(PaymentMethod::CASH_ON_DELIVERY),
                                Select::make('payment_status')
                                    ->label(__('Payment Status'))
                                    ->options(PaymentStatus::class)
                                    ->required()
                                    ->default(PaymentStatus::UNPAID),
                                Select::make('city_id')
                                    ->label(__('City'))
                                    ->options(City::get()->pluck('name', 'id'))
                                    ->searchable()
                                    ->required(),
                                Select::make('district_id')
                                    ->label(__('District'))
                                    ->options(District::get()->pluck('name', 'id'))
                                    ->searchable()
                                    ->required(),
                                Textarea::make('delivery_address')
                                    ->label(__('Delivery Address'))
                                    ->required()
                                    ->rows(3),
                                TextInput::make('contact_phone')
                                    ->required()
                                    ->label(__('Contact Phone')),
                                Textarea::make('notes')
                                    ->label(__('Notes'))
                                    ->rows(3),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    // **** تم تصحيح هذه الدالة أيضاً ****
    public static function updateTotals(Set $set, array $data): void
    {
        // استخراج مصفوفة المنتجات من الداتا، مع وضع قيمة افتراضية (مصفوفة فارغة)
        $items = collect($data['items'] ?? []);

        $subTotal = $items->sum(function ($item) {
            return ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
        });

        // استخراج رسوم التوصيل من الداتا
        $deliveryFee = $data['delivery_fee'] ?? 0;
        $grandTotal = $subTotal + (float)$deliveryFee;

        // تحديث قيم الحقول
        $set('sub_total', number_format($subTotal, 2, '.', ''));
        $set('grand_total', number_format($grandTotal, 2, '.', ''));
    }

    public function save()
    {
        $this->validate([
            'data.user_id' => 'required|exists:users,id',
            'data.items' => 'required|array|min:1',
            'data.items.*.product_id' => 'required|exists:items_products,id',
            'data.items.*.quantity' => 'required|numeric|min:1',
        ]);

        $order_id = Order::count() + 1;
        $now = now();
        $datePart = $now->format('ydm');
        $timePart = $now->format('Hi');
        $newOrderNumber = $datePart . $timePart . $order_id;

        $orderData = $this->form->getState();
        $orderData['order_number'] = $newOrderNumber;
//        $orderData['order_number'] = 'ORD-' . strtoupper(Str::random(8));
        $orderData['created_by'] = auth()->user()->id;

        $order = Order::create($orderData);

        $itemsData = $orderData['items'];
        foreach ($itemsData as $key => $item) {
            $itemsData[$key]['total_price'] = ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
        }

        $order->items()->createMany($itemsData);

        Toaster::success(__('Order added successfully'));

        return $this->redirectRoute('orders.index');
    }

    public function render()
    {
        return view('items::livewire.pages.order.add')
            ->layout(AppLayout::class, [
                'breadcrumbs' => [
                    ['title' => __('Home'), 'url' => route('home')],
                    ['title' => __('Orders List'), 'url' => route('orders.index')],
                    ['title' => __('Add Order'), 'url' => route('orders.add')],
                ]
            ]);
    }
}
