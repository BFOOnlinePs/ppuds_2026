<?php

namespace Modules\Customer\Livewire\Pages\Customer\Details\Tables;

use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Attributes\On;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Core\Entities\CoreTransaction;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Customer\Entities\Customer; // <== 🚨 استيراد كلاس العميل
use Modules\Core\Enums\TransactionFlow; // <== 🚨 استيراد الـ Enum للتدفق

class Transaction extends Component implements HasTable,HasForms
{
    use InteractsWithForms;
    use InteractsWithTable;

    public $customer;

    public function mount($customer)
    {
        // يجب أن يكون $customer كائن موديل Customer، وليس فقط الـ ID
        $this->customer = $customer;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                CoreTransaction::query()
                    ->where('related_entity_type', Customer::class)
                    ->where('related_entity_id', $this->customer->id)
                    ->orderBy('id', 'desc')
            )
            ->columns([
                TextColumn::make('source_type')
                    ->label(__('Transaction Type')),

                TextColumn::make('flow')
                    ->label(__('Flow'))
                    ->badge()
                    ->getStateUsing(fn(CoreTransaction $record) => TransactionFlow::tryFrom($record->flow))
                    ->color(fn(TransactionFlow $state): string => $state->getColor() ?? 'gray'),

                TextColumn::make('description')
                    ->label(__('Description'))
                    ->wrap()
                    ->placeholder('-'),

                TextColumn::make('income_amount')
                    ->label(__('Income'))
                    ->state(function ($record) {
                        return $record->flow === TransactionFlow::INCOME->value ? $record->amount : 0;
                    })
                    ->money('ILS'),

                TextColumn::make('expense_amount')
                    ->label(__('Expense'))
                    ->state(function ($record) {
                        return $record->flow === TransactionFlow::EXPENSE->value ? $record->amount : 0;
                    })
                    ->money('ILS'),

                TextColumn::make('amount')
                    ->label(__('Amount'))
                    ->prefix('ILS')
                    ->sortable(),

                TextColumn::make('transaction_date')
                    ->label(__('Date'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('createdBy.name')
                    ->label(__('Created By')),
            ])
            ->actions([
                DeleteAction::make('delete')
                    ->action(function ($record) {
                        $record->forceDelete();
                        Toaster::success(__('Transaction deleted successfully'));
                    })
            ]);
    }

    #[On('saveDeposit')]
    public function refreshTable(): void
    {
        $this->resetTable();
    }

    public function render()
    {
        return view('customer::livewire.pages.customer.details.tables.transaction');
    }
}
