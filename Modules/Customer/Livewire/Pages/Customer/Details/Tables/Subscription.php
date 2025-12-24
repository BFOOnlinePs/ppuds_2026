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
use Modules\Core\Filament\Forms\Components\DeleteAction;

class Subscription extends Component implements HasTable,HasForms
{
    use InteractsWithForms;
    use InteractsWithTable;

    public $customer;

    public function mount($customer)
    {
        $this->customer = $customer;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(\Modules\Subscription\Entities\Subscription::query()->where('customer_id', $this->customer->id)->orderBy('id', 'desc'))
            ->columns([
                TextColumn::make('plan.name')
                    ->label(__('Plan')),
                TextColumn::make('paid_amount')
                    ->label(__('Paid Amount')),
                TextColumn::make('createdBy.name')
                    ->label(__('Created By')),
                TextColumn::make('start_date')
                    ->label(__('Start Date')),
                TextColumn::make('end_date')
                    ->label(__('End Date')),
            ])
            ->actions([
                DeleteAction::make('delete')
                    ->action(function ($record) {
                        $record->forceDelete();

                        Toaster::success(__('Subscription deleted successfully'));
                    })
//                    ->visible(fn() => auth()->user()->can('Customer Delete')),
            ]);
    }

    #[On('subscriptionSaved')]
    public function refreshTable(): void
    {
        $this->resetTable();
    }

    public function render()
    {
        return view('customer::livewire.pages.customer.details.tables.subscription');
    }
}
