<?php

namespace Modules\Clinic\Livewire\Pages\Program\CustomerProgram;

use App\View\Components\AppLayout;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Actions\Action;
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
use Modules\Clinic\Entities\ProgramCategory;
use Modules\Clinic\Entities\ProgramCustomer;
use Modules\Clinic\Entities\ProgramInstruction;
use Modules\Clinic\Entities\ProgramTypeOfMeal;
use Modules\Clinic\Enums\CustomerProgramStatus;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Nwidart\Modules\Facades\Module;
use Modules\Core\Services\PdfService;
use Omaralalwi\Gpdf\Enums\GpdfSettingKeys;
use Omaralalwi\Gpdf\GpdfConfig;

class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table)
    {
        return $table
            ->query(fn() => ProgramCustomer::query()->with([
                'customer',
                'program',
                'days.dayMeals.mealType',
                'days.dayMeals.mealItems.foodItem',
            ]))
            ->heading(__('Customer Programs'))
            ->emptyStateHeading(__('No customer programs found'))
            ->emptyStateDescription(__('Create a new customer program by clicking the button below'))
            ->emptyStateActions([
//                CreateAction::make()
//                    ->label(__('Add Customer Program'))
//                    ->visible(fn() => auth()->user()->can('Program Create'))
            ])
            ->columns([
                TextColumn::make('customer.user.name')
                    ->label(__('Name'))
                    ->searchable()
                    ->url(function ($record) {
                        return route('customers.details', $record->customer_id);
                    }),

                TextColumn::make('program.name')
                    ->label(__('Program Name'))
                    ->url(function ($record) {
                        return route('program.details.index', $record->program_id);
                    }),

                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge(CustomerProgramStatus::class),

                TextColumn::make('start_date')
                    ->label(__('Start Date'))
            ])
            ->filters($this->getTableFilters())
            ->actions(
                $this->getTableActions()
            )
//            ->headerActions([
//                CreateAction::make('create')
//                    ->label(__('Add Program'))
//                    ->form([
//                        Grid::make(2)
//                            ->schema([
//                                TextInput::make('name')
//                                    ->columnSpanFull()
//                                    ->label(__('Name'))
//                                    ->required(),
//                                Select::make('category_id')
//                                    ->columnSpan(1)
//                                    ->label(__('Category'))
//                                    ->searchable()
//                                    ->required()
//                                    ->options(ProgramCategory::all()->pluck('name', 'id')),
//                                Select::make('instruction_id')
//                                    ->columnSpan(1)
//                                    ->label(__('Instruction'))
//                                    ->searchable()
//                                    ->required()
//                                    ->options(ProgramInstruction::all()->pluck('name', 'id')),
//                                Textarea::make('description')
//                                    ->columnSpanFull()
//                                    ->label(__('Description')),
//                            ])
//                    ])
//                    ->action(function ($data, $action){
//                        $this->authorize('Program Create');
//                        $data['created_by'] = auth()->user()->id;
//                        Program::create($data);
//                        Toaster::success(__('Program created successfully'));
//                        $action->halt();
//                        $action->getForm()->fill();
//                    })
//                    ->visible(fn() => auth()->user()->can('Program Create')),
//            ])
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
        ];
    }

    protected function getTableActions(): array
    {
        return [
            InfoAction::make('info')
                ->label('')
                ->visible(fn() => auth()->user()->can('Customer Program Info')),
            ViewAction::make('view')
                ->form(function (Forms\Form $form, $record) {
                    return $form->schema([
                        Grid::make(1)
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('Name'))
                                    ->default($record->name)
                                    ->disabled(),
                                Textarea::make('description')
                                    ->label(__('Description'))
                                    ->default($record->description)
                                    ->disabled(),
                            ]),
                    ]);
                })
                ->modalSubmitAction(false)
                ->visible(fn() => auth()->user()->can('Customer Program View')),

            Action::make('details')
                ->label('')
                ->size('xl')
                ->tooltip(__('Details'))
                ->color('warning')
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
                ->form(function (Forms\Form $form, $record) {
                    return $form->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->columnSpanFull()
                                    ->label(__('Name'))
                                    ->default($record->name)
                                    ->required(),
                                Select::make('category_id')
                                    ->columnSpan(1)
                                    ->label(__('Category'))
                                    ->searchable()
                                    ->default($record->category_id)
                                    ->required()
                                    ->options(ProgramCategory::all()->pluck('name', 'id')),
                                Select::make('instruction_id')
                                    ->columnSpan(1)
                                    ->label(__('Instruction'))
                                    ->searchable()
                                    ->default($record->instruction_id)
                                    ->required()
                                    ->options(ProgramInstruction::all()->pluck('name', 'id')),
                                Textarea::make('description')
                                    ->columnSpanFull()
                                    ->default($record->description)
                                    ->label(__('Description')),
                            ])
                    ]);
                })
                ->mutateFormDataUsing(function (array $data): array {
                    $data['locale'] = app()->getLocale();
                    return $data;
                })
                ->action(function ($data, $record) {
                    $this->authorize('Program Update');
                    $record->update($data);
                    Toaster::success(__('Program updated successfully'));
                })
                ->visible(fn() => auth()->user()->can('Customer Program Update')),

            DeleteAction::make('delete')
                ->action(function ($record) {
                    $this->authorize('Program Delete');
                    $record->delete();
                    Toaster::success(__('Program deleted successfully'));
                })
                ->visible(fn() => auth()->user()->can('Customer Program Delete')),
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
                    ->visible(fn() => auth()->user()->can('Customer Program Delete')),
            ])
        ];
    }

    public function customerProgramPDF($data)
    {
        $viewData = ['customerProgram' => $data];
        $options = [];
        return app(pdfService::class)->downloadPdf('clinic::livewire.pages.program.customer-program.pdf.customer-program', $viewData,$data->customer->name.'_'.$data->program->name.'.pdf',$options);
    }

    public function render()
    {
        return view('clinic::livewire.pages.program.customer-program.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Customer Programs List'), 'url' => route('program.programs.index')],
            ]
        ]);
    }
}
