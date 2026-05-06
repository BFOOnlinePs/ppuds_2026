<?php

namespace Modules\Items\Livewire\Pages\Offer;

use App\View\Components\AppLayout;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Livewire\Component;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Masmerise\Toaster\Toaster;
use Modules\Branch\Entities\Branch;
use Modules\Core\Filament\Forms\Components\CreateAction;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Offer;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\OfferType;

class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;


    public function table(Table $table)
    {
        return $table
            ->query(fn() => Offer::query()->with('createdBy'))
            ->heading(__('Offers'))
            ->emptyStateHeading(__('No offers found'))
            ->emptyStateDescription(__('Create a new offer by clicking the button below')) // مترجمة
            ->emptyStateActions([
                CreateAction::make()
                    ->label(__('Add Offer'))
                    ->url(fn() => route('offers.add'))
                    ->visible(fn() => auth()->user()->can('Offer Create'))
            ])
            ->columns([
                ImageColumn::make('')
                    ->label(__('Image'))
                    ->getStateUsing(function ($record) {
                        return $record->getImageAttribute();
                    })
                    ->size(60),
                TextColumn::make('name')
                    ->label(__('Name')),
                TextColumn::make('branch.name')
                    ->label(__('Branch')),
                TextColumn::make('description')
                    ->label(__('Description')),
                TextColumn::make('offerable_type')
                    ->label(__('Offerable Type')),
                TextColumn::make('offerable_id')
                    ->label(__('Offerable ID')),
                TextColumn::make('is_active')
                    ->label(__('Active'))
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->formatStateUsing(fn (bool $state): string => $state ? __('Active') : __('Inactive')),
                TextColumn::make('type')
                    ->label(__('Type'))
                    ->badge(OfferType::class),
                TextColumn::make('start_date')
                    ->label(__('Start Date')),
                TextColumn::make('end_date')
                    ->label(__('End Date')),
            ])
            ->actions(
                $this->getTableActions()
            )
            ->headerActions([
                CreateAction::make('create')
                    ->label(__('Add Offer'))
                    ->url(fn() => route('offers.add'))
                    ->visible(fn() => auth()->user()->can('Offer Create')),
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
                        ])
                ])
        ];
    }

    protected function getTableActions(): array
    {
        return [
            InfoAction::make('info')
                ->label('')
                ->visible(fn() => auth()->user()->can('Offer Info')),
            ViewAction::make('view')
                ->form(function (Form $form, $record) {
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
                ->visible(fn() => auth()->user()->can('Offer View')),

            EditAction::make('edit')
                ->label('')
                ->url(fn($record) => route('offers.edit', $record))
                ->visible(fn() => auth()->user()->can('Offer Update')),

            DeleteAction::make('delete')
                ->action(function ($record) {
                    $this->authorize('Offer Delete');
                    $record->delete();
                    Toaster::success(__('offer deleted successfully')); // مترجمة
                })
                ->visible(fn() => auth()->user()->can('Offer Delete')),
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
                    ->visible(fn() => auth()->user()->can('Offer Delete')),
            ])
        ];
    }

    #[Title('Offer List')]
    public function render()
    {
        return view('items::livewire.pages.offer.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Offers List'), 'url' => route('food.items.index')],
            ]
        ]);
    }
}
