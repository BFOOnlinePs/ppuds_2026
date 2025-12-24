<?php

namespace Modules\Clinic\Livewire\Pages\Appointment;

use App\View\Components\AppLayout;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Set;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\SelectAction;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Livewire\Component;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Support\Enums\ActionSize;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Masmerise\Toaster\Toaster;
use Modules\Clinic\Entities\Appointment;
use Modules\Clinic\Entities\Room;
use Modules\Clinic\Enums\AppointmentStatus;
use Modules\Clinic\Enums\PaymentMethod;
use Modules\Core\Entities\CoreTransaction;
use Modules\Core\Enums\TransactionFlow;
use Modules\Core\Enums\TransactionType;
use Modules\Core\Filament\Forms\Components\CreateAction;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\Core\Interfaces\TransactionLoggerInterface;
use Modules\Core\Services\PdfService;
use Modules\Customer\Entities\Customer;

class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table)
    {
        return $table
            ->query(fn() => Appointment::query()->with('createdBy'))
            ->heading(__('Appointments'))
            ->emptyStateHeading(__('No appointments found'))
            ->emptyStateDescription(__('Create a new appointment by clicking the button below')) // مترجمة
            ->emptyStateActions([
                CreateAction::make()
                    ->label(__('Add Appointment'))
                    ->visible(fn() => auth()->user()->can('Appointment Create'))
            ])
            ->columns([
                TextColumn::make('customer.name')
                    ->label(__('Customer')),
                TextColumn::make('room.name')
                    ->label(__('Room')),
                TextColumn::make('room.name')
                    ->label(__('Room')),
                TextColumn::make('date')
                    ->label(__('Date')),
                TextColumn::make('start_time')
                    ->label(__('Start Time')),
                TextColumn::make('end_time')
                    ->label(__('End Time')),
                //                SelectColumn::make('status')
                //                    ->label(__('Status'))
                //                    ->options(AppointmentStatus::class)
                //                    ->rules(['required'])
                SelectColumn::make('status')
                    ->label(__('Status'))
                    ->options(AppointmentStatus::class)
                    ->rules(['required'])
                    ->extraAttributes(function ($record) {
                        // استخدم ->value إذا لم تكن تستخدم casting في المودل
                        $statusValue = $record->status instanceof AppointmentStatus ? $record->status->value : $record->status;

                        $isCompleted = $statusValue == AppointmentStatus::COMPLETED->value;
                        $isCancelled = $statusValue == AppointmentStatus::CANCELLED->value;

                        if ($isCancelled) {
                            return ['class' => 'bg-red-500 dark:bg-red-900'];
                        }

                        if ($isCompleted) {
                            return ['class' => 'bg-green-500 dark:bg-green-900'];
                        }

                        return [];
                    })
            ])
            ->filters($this->getTableFilters(), layout: FiltersLayout::AboveContent)
            ->actions(
                $this->getTableActions()
            )
            ->headerActions([
                CreateAction::make('create')
                    ->label(__('Add Appointment'))
                    ->form([
                        Grid::make(1)
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        Select::make('customer_id')
                                            ->label(__('Customer'))
                                            ->columnSpanFull()
                                            ->searchable()
                                            ->options(Customer::get()->pluck('name', 'id'))
                                            ->required(),

                                        DatePicker::make('date')
                                            ->label(__('Date'))
                                            ->formatStateUsing(function ($state) {
                                                return Carbon::parse($state)->format('Y-m-d');
                                            })
                                            ->required(),

                                        Select::make('room_id')
                                            ->label(__('Room'))
                                            ->default(Room::first()->id)
                                            ->options(Room::get()->pluck('name', 'id'))
                                            ->required(),

                                        TimePicker::make('start_time')
                                            ->label(__('Start Time'))
                                            ->formatStateUsing(function ($state) {
                                                return Carbon::parse($state)->format('H:i');
                                            })
                                            ->live()
                                            ->afterStateUpdated(function (Set $set, string $state) {
                                                $setEndTime = Carbon::parse($state)->addMinute(15)->format('H:i');
                                                $set('end_time', $setEndTime);
                                            })
                                            ->required(),

                                        TimePicker::make('end_time')
                                            ->label(__('End Time'))
                                            ->formatStateUsing(function ($state) {
                                                return Carbon::parse($state)->addMinute(15)->format('H:i');
                                            })
                                            ->required(),
                                    ])
                            ])
                    ])
                    ->using(function ($data, CreateAction $action) {
                        $this->authorize('Appointment Create');
                        $data['created_by'] = auth()->id();
                        return Appointment::create($data);
                    })
                    ->after(function () {
                        Toaster::success(__('Appointment created successfully'));
                    })
                    ->visible(fn() => auth()->user()->can('Appointment Create')),
            ])
            ->bulkActions($this->getTableBulkAction());
    }

    protected function getTableFilters(): array
    {
        return [
            Filter::make('name')
                ->columnSpanFull()
                ->form([
                    Grid::make(12)
                        ->schema([
                            TextInput::make('name')
                                ->columnSpan(2)
                                ->label(__('Name'))
                                ->placeholder(__('Search...')),
                            Select::make('room_id')
                                ->columnSpan(2)
                                ->label(__('Room'))
                                ->placeholder(__('Search...'))
                                ->options(Room::get()->pluck('name', 'id')),
                            Select::make('status')
                                ->columnSpan(2)
                                ->label(__('Status'))
                                ->placeholder(__('Search...'))
                                ->options(AppointmentStatus::class),
                            DatePicker::make('date')
                                ->columnSpan(2)
                                ->default(now())
                                ->label(__('Date'))
                                ->placeholder(__('Search...')),
                            TimePicker::make('start_time')
                                ->columnSpan(2)
                                ->label(__('Start Time'))
                                ->placeholder(__('Search...')),
                            TimePicker::make('end_time')
                                ->columnSpan(2)
                                ->label(__('End Time'))
                                ->placeholder(__('Search...')),
                        ])
                ])
                ->query(function (Builder $query, array $data) {
                    if (!empty($data['name'])) {
                        $query->whereHas('customer', function ($query) use ($data) {
                            $query->where('first_name', 'like', '%' . $data['name'] . '%')->orWhere('last_name', 'like', '%' . $data['name'] . '%');
                        });
                    }
                    if (!empty($data['room_id'])) {
                        $query->where('room_id', $data['room_id']);
                    }
                    if (!empty($data['status'])) {
                        $query->where('status', $data['status']);
                    }
                    if (!empty($data['date'])) {
                        $query->where('date', $data['date']);
                    }
                    if (!empty($data['start_time'])) {
                        $query->where('start_time', $data['start_time']);
                    }
                    if (!empty($data['end_time'])) {
                        $query->where('end_time', $data['end_time']);
                    }
                }),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            InfoAction::make('info')
                ->label('')
                ->visible(fn() => auth()->user()->can('Appointment Info')),

            Action::make('add_deposit')
                ->label(__('Add Deposit'))
                ->size(ActionSize::Large)
                ->tooltip(__('Add Prepayment Deposit'))
                ->color('success')
                ->form(function (Form $form, $record) {
                    return $form->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('amount')
                                    ->label(__('Deposit Amount'))
                                    ->required()
                                    ->numeric()
                                    ->prefix('₪'),
                                Select::make('payment_method')
                                    ->label(__('Payment Method'))
                                    ->required()
                                    ->options(PaymentMethod::options())
                                    ->live()
                                    ->searchable(),
                                DatePicker::make('date')
                                    ->label(__('Payment Date'))
                                    ->default(now()->format('Y-m-d'))
                                    ->required(),
                                TextInput::make('reference_no')
                                    ->label(__('Reference Number'))
                                    ->columnSpanFull()
                                    ->nullable()
                                    ->visible(fn(Get $get) => $get('payment_method') == PaymentMethod::BANK_TRANSFER->value || $get('payment_method') == PaymentMethod::CREDIT_CARD->value),
                                Textarea::make('notes')
                                    ->columnSpanFull()
                                    ->label(__('Notes')),

                                ViewField::make('transactions_view')
                                    ->view('customer::components.transactions', ['customer' => $record])
                                    ->columnSpanFull(),
                            ]),
                    ]);
                })
                ->action(function (array $data, $record) {
                    $amount = $data['amount'];
                    $paymentMethod = $data['payment_method'];
                    $referenceNo = $data['reference_no'] ?? null;
                    $notes = $data['notes'] ?? '';

                    app(TransactionLoggerInterface::class)->log(
                        sourceDocument: $record,
                        flow: TransactionFlow::INCOME->value,
                        amount: $amount,
                        paymentMethod: $paymentMethod,
                        referenceNo: $referenceNo,
                        sourceTypeEnum: TransactionType::PrepaymentDeposit,
                        description: __('Customer added a prepayment deposit.') . " " . $notes,
                        relatedEntity: $record->customer,
                    );

                    Toaster::success(__('Deposit added successfully'));
                })
                ->visible(fn() => auth()->user()->can('Customer Deposit Create')),

            Action::make('print_invoice')
                ->label(__('Print Invoice'))
                ->size(ActionSize::Large)
                ->tooltip(__('Print Appointment Invoice'))
                ->color('primary')
                ->action(function ($record) {

                    $transactions = CoreTransaction::where('related_entity_id', $record->customer_id)
                        ->where('transactionable_type', Appointment::class)
                        // ->where('transactionable_id', $record->id)
                        ->get();

                    return app(PdfService::class)->downloadPdf(
                        view: 'clinic::pdf.appointments.appointment-invoice',
                        data: ['appointment' => $record , 'transactions' => $transactions],
                        filename: 'appointment_invoice_' . $record->id . '.pdf',
                    );
                })
                ,
                // ->visible(fn() => auth()->user()->can('Appointment Invoice'))
                // ,

            //            ViewAction::make('view')
            //                ->form(function (Forms\Form $form, $record) {
            //                    return $form->schema([
            //                        Grid::make(1)
            //                            ->schema([
            //                                TextInput::make('name')
            //                                    ->label(__('Name'))
            //                                    ->default($record->name)
            //                                    ->disabled(),
            //                                Textarea::make('description')
            //                                    ->label(__('Description'))
            //                                    ->default($record->description)
            //                                    ->disabled(),
            //                            ]),
            //                    ]);
            //                })
            //                ->modalSubmitAction(false)
            //                ->visible(fn() => auth()->user()->can('Appointment View')),

            DeleteAction::make('delete')
                ->action(function ($record) {
                    $this->authorize('Appointment Delete');
                    $record->delete();
                    Toaster::success(__('appointment deleted successfully'));
                })
                ->visible(fn() => auth()->user()->can('Appointment Delete')),
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
                    ->visible(fn() => auth()->user()->can('Appointment Delete')),
            ])
        ];
    }

    public function render()
    {
        return view('clinic::livewire.pages.appointment.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Food Item List'), 'url' => route('food.items.index')],
            ]
        ]);
    }
}
