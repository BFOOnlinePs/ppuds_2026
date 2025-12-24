<?php

namespace Modules\Subscription\Livewire\Pages;

use App\View\Components\AppLayout;
use Filament\Forms\Concerns\InteractsWithForms;
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
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Masmerise\Toaster\Toaster;
use Modules\Core\Filament\Forms\Components\CreateAction;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\Customer\Entities\Customer;
use Modules\Customer\Enums\Status;
use Filament\Tables\Actions\Action;
use Modules\Subscription\Entities\Plan;
use Modules\Subscription\Entities\Subscription;
use Nwidart\Modules\Facades\Module;

class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table)
    {
        return $table
            ->query(fn() => Plan::query()->with('createdBy'))
            ->heading(__('Subscriptions'))
            ->emptyStateHeading(__('No subscriptions found'))
            ->emptyStateDescription(__('Create a new subscription by clicking the button below'))
            ->emptyStateActions([
                CreateAction::make()
                    ->label(__('Add subscription'))

                    ->visible(fn() => auth()->user()->can('Subscription Create'))
            ])
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name')),
                TextColumn::make('price')
                    ->label(__('Price')),
                TextColumn::make('duration')
                    ->label(__('Duration')),
                TextColumn::make('is_active')
                    ->label(__('Is Active'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state == 1 ? __('Active') : __('Inactive'))
                    ->colors([
                        'success' => 1,
                        'danger' => 0,
                    ]),
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
                    ->label(__('Add subscription'))
                    ->form([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->columnSpanFull()
                                    ->label(__('Name'))
                                    ->required(),
                                TextInput::make('price')
                                    ->columnSpan(1)
                                    ->label('Price')
                                    ->required()
                                    ->numeric(),
                                TextInput::make('duration')
                                    ->columnSpan(1)
                                    ->label(__('Duration'))
                                    ->required()
                                    ->numeric(),
                                Textarea::make('description')
                                    ->columnSpanFull()
                                    ->label(__('Description')),
                            ])
                    ])
                    ->action(function ($data){
                        $this->authorize('Subscription Create');

                        $data['created_by'] = auth()->user()->id;

                        $plans = Plan::create($data);
                    })
                    ->visible(fn() => auth()->user()->can('Subscription Create')),
            ])
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
                ->visible(fn() => auth()->user()->can('Subscription Info')),
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
                ->visible(fn() => auth()->user()->can('Subscription View')),

//            Action::make('subscription')
//                ->label('')
//                ->size('xl')
//                ->color('warning')
//                ->icon('solar-card-bold')
//                ->form([
//                    TextInput::make('name')
//                ])
//                ->visible(fn() => Module::isEnabled('subscription')),


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
                                TextInput::make('price')
                                    ->columnSpan(1)
                                    ->label('Price')
                                    ->required()
                                    ->default($record->price)
                                    ->numeric(),
                                TextInput::make('duration')
                                    ->columnSpan(1)
                                    ->label(__('Duration'))
                                    ->required()
                                    ->default($record->duration)
                                    ->numeric(),
                                Textarea::make('description')
                                    ->columnSpanFull()
                                    ->default($record->description)
                                    ->label(__('Description')),
                            ])
                    ]);
                })
                ->action(function ($data , $record){

                    $record->update($data);
                })
                ->visible(fn() => auth()->user()->can('Subscription Update')),

            DeleteAction::make('delete')
                ->action(function ($record) {
                    $record->delete();

                    Toaster::success(__('Customer deleted successfully'));
                })
                ->visible(fn() => auth()->user()->can('Subscription Delete')),
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
                    ->visible(fn() => auth()->user()->can('Subscription Delete')),
            ])
        ];
    }

    public function render()
    {
        return view('subscription::livewire.pages.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Subscriptions List'), 'url' => route('subscriptions.index')],
            ]
        ]);
    }
}
