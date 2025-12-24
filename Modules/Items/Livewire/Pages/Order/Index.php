<?php

namespace Modules\Items\Livewire\Pages\Order;

use App\View\Components\AppLayout;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Livewire\Component;
use Filament\Forms;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Set;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Jantinnerezo\LivewireAlert\LivewireAlert;
use Masmerise\Toaster\Toaster;
use Modules\Core\Filament\Forms\Components\CreateAction;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\Items\Entities\Addon;
use Modules\Items\Entities\Brand;
use Modules\Items\Entities\Order;
use Modules\Items\Enums\AddonType;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Enums\PaymentMethod;
use Modules\Items\Enums\PaymentStatus;

class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table)
    {
        return $table
            ->query(fn() => Order::query()->with('createdBy','items.product'))
            ->heading(__('Orders'))
            ->emptyStateHeading(__('No orders found'))
            ->emptyStateDescription(__('Create a new order by clicking the button below'))
            ->emptyStateActions([
                CreateAction::make()
                    ->label(__('Add Order'))
                    ->url(fn() => route('orders.add'))
                    ->visible(fn() => auth()->user()->can('Order Create'))
            ])
            ->columns([
                TextColumn::make('order_number')
                    ->label(__('Order Number'))
                    ->color('primary')
                    ->icon('solar-pen-new-square-bold')
                    ->url(function ($record) {
                        return route('orders.edit', $record);
                    }),
                TextColumn::make('user.name')
                    ->label(__('Name')),
                TextColumn::make('sub_total')
                    ->label(__('Sub Total')),
                TextColumn::make('discount')
                    ->label(__('Discount')),
                TextColumn::make('delivery_fee')
                    ->label(__('Delivery Fee')),
                TextColumn::make('total')
                    ->label(__('Total')),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(OrderStatus::class),
                TextColumn::make('payment_status')
                    ->label(__('Payment Status'))
                    ->badge()
                    ->color(PaymentStatus::class),
            ])
            ->filters(
                filters: $this->getTableFilters(),
                layout: FiltersLayout::AboveContent,
            )
            ->actions(
                $this->getTableActions()
            )
            ->headerActions([
                CreateAction::make('create')
                    ->label(__('Add Order'))
                    ->url(fn() => route('orders.add'))
                    ->visible(fn() => auth()->user()->can('Order Create'))
            ])
            ->bulkActions($this->getTableBulkAction());
    }

    protected function getTableFilters(): array
    {
        return [
            Filter::make('order_number')
                ->form([
                    TextInput::make('order_number')
                        ->label(__('Order Number'))
                        ->placeholder(__('Search by Order Number'))
                ])
                ->query(function (Builder $query, array $data) {
                    if (!empty($data['order_number'])) {
                        $query->where('order_number', 'like', '%' . $data['order_number'] . '%');
                    }
                }),
            Filter::make('name')
                ->form([
                    TextInput::make('name')
                        ->label(__('Name'))
                        ->placeholder(__('Search by Client Number'))
                ])
                ->query(function (Builder $query, array $data) {
                    if (!empty($data['name'])) {
                        $query->whereHas('user', function (Builder $query) use ($data) {
                            $query->where('name', 'like', '%' . $data['name'] . '%');
                        });
                    }
                }),
            Filter::make('payment_status')
                ->form([
                    Select::make('payment_status')
                        ->label(__('Payment Status'))
                        ->options(PaymentStatus::class)
                        ->placeholder(__('Search by Payment Status'))
                ])
                ->query(function (Builder $query, array $data) {
                    if (!empty($data['payment_status'])) {
                        $query->where('payment_status', $data['payment_status']);
                    }
                }),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            // ActivityLogAction::make('activity_log')->label(__('Activity Log')),
            InfoAction::make('info')
                ->label('')
                ->visible(fn() => auth()->user()->can('Order Info')),

            ViewAction::make('view')
                ->label('')
                ->form(function ($form, Order $record) {
                    return $form
                        ->disabled()
                        ->schema([
                            Grid::make(3)
                                ->schema([
                                    Section::make(__('Order Details'))
                                        ->columnSpan(2)
                                        ->schema([
                                            Grid::make(2)->schema([
                                                TextInput::make('user.name')
                                                    ->default($record->user->name)
                                                    ->label(__('Customer Name')),
                                                TextInput::make('order_number')
                                                    ->default($record->order_number)
                                                    ->label(__('Order Number')),
                                            ]),

                                            Forms\Components\Repeater::make('items')
                                                ->label(__('Products'))
                                                ->default($record->items->toArray())
                                                ->schema([
                                                    TextInput::make('product.name')
                                                    ->label(__('Product')),
                                                    TextInput::make('quantity')
                                                        ->label(__('Quantity')),
                                                    TextInput::make('price')
                                                        ->label(__('Price')),
                                                    TextInput::make('total_price')
                                                        ->label(__('Total'))

                                                ])
                                                ->columns(4), // 4 أعمدة للمنتجات

                                            // عرض المجاميع النهائية
                                            Section::make(__('Totals'))
                                                ->schema([
                                                    Placeholder::make('sub_total')
                                                        ->label(__('Subtotal'))
                                                        ->content(fn ($record) => number_format($record->sub_total, 2) . ' SAR'),

                                                    Placeholder::make('delivery_fee')
                                                        ->label(__('Delivery Fee'))
                                                        ->content(fn ($record) => number_format($record->delivery_fee, 2) . ' SAR'),

                                                    Placeholder::make('grand_total')
                                                        ->label(__('Grand Total'))
                                                        ->content(fn ($record) => number_format($record->grand_total, 2) . ' SAR')
                                                        ->extraAttributes(['class' => 'text-lg font-bold']), // لتمييز الإجمالي
                                                ])
                                                ->columns(3),
                                        ]),

                                    Section::make(__('Order Information'))
                                        ->columnSpan(1)
                                        ->schema([
                                            TextInput::make('status')
                                                ->label(__('Order Status'))
                                                ->default($record->status->getLabel()),

                                            TextInput::make('payment_method')
                                                ->label(__('Payment Method'))
                                                ->default($record->payment_method->getLabel()),

                                            TextInput::make('payment_status')
                                                ->label(__('Payment Status'))
                                                ->default($record->payment_status->getLabel()),

                                            Textarea::make('delivery_address')
                                                ->label(__('Delivery Address'))
                                                ->default($record->delivery_address)
                                                ->rows(3),

                                            TextInput::make('contact_phone')
                                                ->default($record->contact_phone)
                                                ->label(__('Contact Phone')),

                                            Textarea::make('notes')
                                                ->label(__('Notes'))
                                                ->default($record->notes)
                                                ->rows(3),

                                            Placeholder::make('created_at')
                                                ->label(__('Order Date'))
                                                ->content(fn ($record) => $record->created_at->format('Y-m-d h:i A')),
                                        ]),
                                ]),
                        ]);
                })
                ->modalHeading(__('View Order'))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('Close'))
                ->visible(fn() => auth()->user()->can('Order View')),
            EditAction::make('edit')
                ->label('')
                ->url(fn($record) => route('orders.edit', $record))
                ->visible(fn() => auth()->user()->can('Order Update')),

            DeleteAction::make('delete')
                ->action(function ($record) {
                    $record->forceDelete();

                    Toaster::success(__('Order deleted successfully'));
                })
                ->visible(fn() => auth()->user()->can('Order Delete')),
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
                    ->visible(fn() => auth()->user()->can('Order Delete')),
            ])
        ];
    }

    public function render()
    {
        return view('items::livewire.pages.order.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Orders List'), 'url' => route('orders.index')],
            ]
        ]);
    }
}
