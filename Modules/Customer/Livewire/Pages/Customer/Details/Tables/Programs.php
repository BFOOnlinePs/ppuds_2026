<?php

namespace Modules\Customer\Livewire\Pages\Customer\Details\Tables;

use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Clinic\Entities\ProgramCustomer;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\Core\Services\PdfService;
use Modules\Customer\Entities\Customer;
use Modules\Customer\Enums\Status;
use Nwidart\Modules\Facades\Module;

class Programs extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    public $customer;

    public function mount(Customer $customer)
    {
        $this->customer = $customer;
    }

    public function table(Table $table)
    {
        return $table
            ->query(fn() => ProgramCustomer::query()->where('customer_id', $this->customer->id)->with('createdBy','program','customer','customer.readings'))
            ->heading(__('Customers'))
            ->emptyStateHeading(__('No customers found'))
            ->columns([
                TextColumn::make('program.name')
                    ->label(__('Name'))
                    ->color('primary')
                    ->icon('solar-pen-new-square-bold')
                    ->url(function ($record) {
                        return route('program.customer-programs.details', $record);
                    }),
                TextColumn::make('start_date')
                    ->label(__('Start Date')),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->colors([
                        'success' => Status::ACTIVE,
                        'danger' => Status::INACTIVE,
                    ]),
         ])
            ->filters($this->getTableFilters())
            ->actions(
                $this->getTableActions()
            )
            ->bulkActions($this->getTableBulkAction());
    }

    /*************  ✨ Windsurf Command ⭐  *************/
    /**
     * The table filters.
     *
     * @return array
     */
    /*******  923c272c-8fa7-4881-8754-4bd584d02cc1  *******/
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
        ];
    }

    protected function getTableActions(): array
    {
        return [
            // ActivityLogAction::make('activity_log')->label(__('Activity Log')),
            InfoAction::make('info')
                ->label('')
                ->visible(fn() => auth()->user()->can('Customer Info')),
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
                ->visible(fn() => auth()->user()->can('Customer View')),

            Action::make('details')
                ->label('')
                ->size('xl')
                ->color('warning')
                ->tooltip(__('Details'))
                ->icon('solar-map-point-search-bold')
                ->url(fn($record) => route('program.customer-programs.details', $record))
                ->visible(fn() => Module::isEnabled('subscription')),

            Action::make('print')
                ->label('')
                ->size('xl')
                ->color('dark')
                ->tooltip(__('Print'))
                ->icon('solar-printer-bold')
                ->action(function ($record) {
                    return $this->customerProgramPDF($record);
                })
                ->visible(fn() => Module::isEnabled('subscription') && auth()->user()->can('Customer Program Print')),

            EditAction::make('edit')
                ->label('')
                ->url(fn($record) => route('customers.edit', $record))
                ->visible(fn() => auth()->user()->can('Currency Update')),

            DeleteAction::make('delete')
                ->action(function ($record) {
                    $record->forceDelete();

                    Toaster::success(__('Customer deleted successfully'));
                })
                ->visible(fn() => auth()->user()->can('Customer Delete')),
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
                    ->visible(fn() => auth()->user()->can('Customer Delete')),
            ])
        ];
    }

    #[On('programSaved')]
    public function refreshTable(): void
    {
        $this->resetTable();
    }

    public function customerProgramPDF($data)
    {
        $data->load([
            'days.dayMeals.mealType',
            'days.dayMeals.mealItems.foodItem',
            'days.dayMeals.mealItems.servingSize'
        ]);

        $readings = $data->customer->readings()->latest()->get();
        $currentReading = $readings->first();
        $previousReading = $readings->skip(1)->first();
        $firstReading = $readings->last();
        $viewData = [
            'customerProgram' => $data,
            'currentReading'  => $currentReading,
            'previousReading' => $previousReading,
            'firstReading'    => $firstReading,
            'metrics'         => [
                'weight'  => 'الوزن',
                'fats'    => 'الدهون',
                'water'   => 'السوائل',
                'muscles' => 'العضلات',
                'salts'   => 'الاملاح',
            ],
        ];
        $options = [];

        return app(pdfService::class)->downloadPdf('clinic::livewire.pages.program.customer-program.pdf.customer-program', $viewData,$data->customer->name.'_'.$data->program->name.'.pdf',$options);
    }

    public function render()
    {
        return view('customer::livewire.pages.customer.details.tables.programs');
    }
}
