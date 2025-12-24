<?php

namespace Modules\Marketing\Livewire\Pages\LoyaltyRules;

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
use Modules\Marketing\Enums\LoyaltyRuleType;
use Nwidart\Modules\Facades\Module;

class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn() => LoyaltyRule::query()->with('createdBy' , 'translations'))
            ->heading(__('Loyalty Rules'))
            ->emptyStateHeading(__('No loyalty rules found'))
            ->emptyStateDescription(__('Create a new loyalty rule by clicking the button below'))
            ->emptyStateActions([
                CreateAction::make()
                    ->label(__('Add Loyalty Rule'))
                    ->visible(fn() => auth()->user()->can('Loyalty Rules Create'))
            ])
            ->columns([
                TextColumn::make('name')
                    ->searchable(
                        query:function (Builder $query, string $search): Builder {
                            return $query->whereTranslationLike('name', '%' . $search . '%');
                        }
                    )
                    ->label(__('Name')),
                TextColumn::make('module')
                    ->label(__('Module')),
                TextColumn::make('action')
                    ->label(__('Action')),
                TextColumn::make('type')
                    ->label(__('Type'))
                    ->badge(),
                TextColumn::make('points_rate')
                    ->label(__('Points Rate')),
                TextColumn::make('fixed_points')
                    ->label(__('Fixed Points')),
                TextColumn::make('min_amount')
                    ->label(__('Min Amount')),
                TextColumn::make('starts_at')
                    ->label(__('Start Date')),
                TextColumn::make('ends_at')
                    ->label(__('End Date')),
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
                    ->label(__('Add Loyalty Rule'))
                    ->form([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('Name'))
                                    ->required()
                                    ->columnSpanFull(),

                                Select::make('module')
                                    ->label(__('Module'))
                                    ->options(function () {
                                        return collect(Module::all())
                                        ->mapWithKeys(function ($module) {
                                            return [$module->getName() => $module->getName()];
                                        })
                                            ->toArray();
                                    })
                                    ->required()
                                    ->columnSpan(1),

                                TextInput::make('action')
                                    ->label(__('Action'))
                                    ->helperText(__('e.g., order_completed, booking_confirmed'))
                                    ->required()
                                    ->columnSpan(1),

                                Select::make('type')
                                    ->label(__('Rule Type'))
                                    ->options(LoyaltyRuleType::class)
                                    ->required()
                                    ->live()
                                    ->columnSpanFull(),

                                TextInput::make('points_rate')
                                    ->label(__('Points Rate'))
                                    ->helperText(__('How much money equals 1 point? e.g., 25'))
                                    ->numeric()
                                    ->required()
                                    ->visible(fn (Get $get) => $get('type') === LoyaltyRuleType::BASE_RATE->value)
                                    ->columnSpan(1),

                                TextInput::make('fixed_points')
                                    ->label(__('Fixed Points'))
                                    ->helperText(__('The fixed amount of points to award.'))
                                    ->numeric()
                                    ->required()
                                    ->visible(fn (Get $get) => $get('type') === LoyaltyRuleType::FIXED_BONUS->value)
                                    ->columnSpan(1),

                                TextInput::make('min_amount')
                                    ->label(__('Minimum Amount'))
                                    ->helperText(__('The rule applies if the amount is greater than or equal to this value.'))
                                    ->numeric()
                                    ->default(0)
                                    ->columnSpanFull(),

                                // استخدام DateTimePicker لإدخال تاريخ ووقت
                                Forms\Components\DateTimePicker::make('starts_at')
                                    ->label(__('Starts At'))
                                    ->columnSpan(1),

                                Forms\Components\DateTimePicker::make('ends_at')
                                    ->label(__('Ends At'))
                                    ->columnSpan(1),

                                Toggle::make('is_active')
                                    ->label(__('Active'))
                                    ->default(true)
                                    ->columnSpanFull(),
                            ])
                    ])
                    ->using(function ($data , $action){
                        $this->authorize('Branch Create');
                        $data['created_by'] = auth()->id();
                        $loyaltyRule = LoyaltyRule::create($data);
                        return $loyaltyRule;
                    })
                    ->after(function (){
                        Toaster::success(__('Loyalty Rule created successfully'));
                    })
                    ->visible(fn() => auth()->user()->can('Loyalty Rules Create')),
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
                ->visible(fn() => auth()->user()->can('Loyalty Rules Info')),
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

                            TextInput::make('module')
                                ->label(__('Module'))
                                ->default($record->module)
                                ->disabled(),

                            TextInput::make('action')
                                ->label(__('Action'))
                                ->default($record->action)
                                ->disabled(),

                            TextInput::make('type')
                                ->label(__('Rule Type'))
                                ->default($record->type->getLabel())
                                ->disabled(),

                            TextInput::make('min_amount')
                                ->label(__('Minimum Amount'))
                                ->default($record->min_amount)
                                ->numeric()
                                ->disabled(),

                            TextInput::make('points_rate')
                                ->label(__('Points Rate'))
                                ->default($record->points_rate)
                                ->numeric()
                                ->visible($record->type === \Modules\Marketing\Enums\LoyaltyRuleType::BASE_RATE) // Show only if type is BASE_RATE
                                ->disabled(),

                            TextInput::make('fixed_points')
                                ->label(__('Fixed Points'))
                                ->default($record->fixed_points)
                                ->numeric()
                                ->visible($record->type === \Modules\Marketing\Enums\LoyaltyRuleType::FIXED_BONUS) // Show only if type is FIXED_BONUS
                                ->disabled(),

                            Forms\Components\DateTimePicker::make('starts_at')
                                ->label(__('Starts At'))
                                ->default($record->starts_at)
                                ->disabled(),

                            Forms\Components\DateTimePicker::make('ends_at')
                                ->label(__('Ends At'))
                                ->default($record->ends_at)
                                ->disabled(),

                            Toggle::make('is_active')
                                ->label(__('Active'))
                                ->default($record->is_active)
                                ->disabled()
                                ->columnSpanFull(),
                        ]),
                    ]);
                })
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('Close'))
                ->visible(fn() => auth()->user()->can('Loyalty Rules View')),

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

                                Select::make('module')
                                    ->label(__('Module'))
                                    ->options(function () {
                                        // This logic is copied from your CreateAction
                                        return collect(Module::all())
                                            ->mapWithKeys(function ($module) {
                                                return [$module->getName() => $module->getName()];
                                            })
                                            ->toArray();
                                    })
                                    ->required()
                                    ->default($record->module)
                                    ->columnSpan(1),

                                TextInput::make('action')
                                    ->label(__('Action'))
                                    ->helperText(__('e.g., order_completed, booking_confirmed'))
                                    ->required()
                                    ->default($record->action)
                                    ->columnSpan(1),

                                Select::make('type')
                                    ->label(__('Rule Type'))
                                    ->options(LoyaltyRuleType::class)
                                    ->required()
                                    ->live() // 'live' is crucial for conditional fields to work
                                    ->default($record->type)
                                    ->columnSpanFull(),

                                TextInput::make('points_rate')
                                    ->label(__('Points Rate'))
                                    ->helperText(__('How much money equals 1 point? e.g., 25'))
                                    ->numeric()
                                    ->required()
                                    ->visible(fn (Get $get) => $get('type') === LoyaltyRuleType::BASE_RATE->value)
                                    ->default($record->points_rate)
                                    ->columnSpan(1),

                                TextInput::make('fixed_points')
                                    ->label(__('Fixed Points'))
                                    ->helperText(__('The fixed amount of points to award.'))
                                    ->numeric()
                                    ->required()
                                    ->visible(fn (Get $get) => $get('type') === LoyaltyRuleType::FIXED_BONUS->value)
                                    ->default($record->fixed_points)
                                    ->columnSpan(1),

                                TextInput::make('min_amount')
                                    ->label(__('Minimum Amount'))
                                    ->helperText(__('The rule applies if the amount is greater than or equal to this value.'))
                                    ->numeric()
                                    ->default($record->min_amount)
                                    ->columnSpanFull(),

                                Forms\Components\DateTimePicker::make('starts_at')
                                    ->label(__('Starts At'))
                                    ->default($record->starts_at)
                                    ->columnSpan(1),

                                Forms\Components\DateTimePicker::make('ends_at')
                                    ->label(__('Ends At'))
                                    ->default($record->ends_at)
                                    ->columnSpan(1),

                                Toggle::make('is_active')
                                    ->label(__('Active'))
                                    ->default($record->is_active)
                                    ->columnSpanFull(),
                            ])
                    ]);
                })
                ->action(function (array $data, LoyaltyRule $record){
                    $this->authorize('Loyalty Rules Update');
                    $record->update($data);
                    Toaster::success(__('Loyalty Rule updated successfully'));
                })
                ->visible(fn() => auth()->user()->can('Loyalty Rules Update')),

            DeleteAction::make('delete')
                ->action(function ($record) {
                    $this->authorize('Loyalty Rules Delete');
                    $record->delete();
                    Toaster::success(__('loyalty rule deleted successfully')); // مترجمة
                })
                ->visible(fn() => auth()->user()->can('Loyalty Rules Delete')),
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
                    ->visible(fn() => auth()->user()->can('Loyalty Rule Delete')),
            ])
        ];
    }

    public function render()
    {
        return view('marketing::livewire.pages.loyalty-rules.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Loyalty Rules List'), 'url' => route('loyalty-rules.index')],
            ]
        ]);
    }
}
