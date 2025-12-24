<?php

namespace Modules\Marketing\Livewire\Pages\LoyaltyTiers;

use App\View\Components\AppLayout;
use Astrotomic\Translatable\Validation\Rules\TranslatableUnique;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Get;
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
use Modules\Branch\Enums\BranchStatus;
use Modules\Branch\Entities\Branch;
use Modules\Core\Filament\Forms\Components\CreateAction;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Filament\Forms\Components\Toggle;
use Modules\GeoLocation\Entities\City;
use Modules\GeoLocation\Entities\Country;
use Modules\Marketing\Entities\LoyaltyRule;
use Modules\Marketing\Entities\LoyaltyTier;
use Modules\Marketing\Enums\LoyaltyRuleType;
use Nwidart\Modules\Facades\Module;

class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn() => LoyaltyTier::query()->with('createdBy' , 'translations'))
            ->heading(__('Loyalty Tiers'))
            ->emptyStateHeading(__('No loyalty tiers found'))
            ->emptyStateDescription(__('Create a new loyalty tier by clicking the button below'))
            ->emptyStateActions([
                CreateAction::make()
                    ->label(__('Add Loyalty Tier'))
                    ->visible(fn() => auth()->user()->can('Loyalty Tier Create'))
            ])
            ->columns([
                TextColumn::make('name')
                    ->searchable(
                        query:function (Builder $query, string $search): Builder {
                            return $query->whereTranslationLike('name', '%' . $search . '%');
                        }
                    )
                    ->label(__('Name')),

                TextColumn::make('min_points')
                    ->label(__('Minimum points')),

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
                    ->label(__('Add Loyalty Tier'))
                    ->form([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('Name'))
                                    ->required()
                                    ->columnSpanFull(),

                            ])
                    ])
                    ->using(function ($data , $action){
                        $this->authorize('Loyalty Tier Create');
                        $data['created_by'] = auth()->id();
                        $loyaltyTier = LoyaltyTier::create($data);
                        return $loyaltyTier;
                    })
                    ->after(function (){
                        Toaster::success(__('Loyalty Tier created successfully'));
                    })
                    ->visible(fn() => auth()->user()->can('Loyalty Tier Create')),
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
                ->visible(fn() => auth()->user()->can('Loyalty Tiers Info')),
            ViewAction::make('view')
                ->label('') // Use an empty label if you prefer just the icon
                ->form(function (Forms\Form $form, $record) {
                    return $form->schema([
                        Grid::make(2) // Using a 2-column grid for better layout
                        ->schema([
                            TextInput::make('name')
                                ->label(__('Name'))
                                ->default($record->name)
                                ->columnSpanFull()
                                ->disabled(),

                        ]),
                    ]);
                })
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('Close'))
                ->visible(fn() => auth()->user()->can('Loyalty Tiers View')),

            EditAction::make('edit')
                ->label('')
                ->form(function (Forms\Form $form, $record) {
                    return $form->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('Name'))
                                    ->required()
                                    ->default($record->name)
                                    ->columnSpanFull(),

                            ])
                    ]);
                })
                ->action(function (array $data, LoyaltyTier $record){
                    $this->authorize('Loyalty Tiers Update');
                    $record->update($data);
                    Toaster::success(__('Loyalty Tier updated successfully'));
                })
                ->visible(fn() => auth()->user()->can('Loyalty Tiers Update')),

            DeleteAction::make('delete')
                ->action(function ($record) {
                    $this->authorize('Loyalty Tiers Delete');
                    $record->delete();
                    Toaster::success(__('loyalty tier deleted successfully')); // مترجمة
                })
                ->visible(fn() => auth()->user()->can('Loyalty Tiers Delete')),
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
                    ->visible(fn() => auth()->user()->can('Loyalty Tier Delete')),
            ])
        ];
    }

    public function render()
    {
        return view('marketing::livewire.pages.loyalty-tiers.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Loyalty Tiers List'), 'url' => route('loyalty-tiers.index')],
            ]
        ]);
    }
}
