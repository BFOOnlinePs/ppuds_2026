<?php

namespace Modules\Coupon\Livewire\Pages\Coupon;

use App\View\Components\AppLayout;
use Dom\Text;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Livewire\Component;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Forms\Components\CreateAction;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\Items\Entities\Category;
use Modules\Coupon\Entities\Coupon;

class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table)
    {
        return $table
            ->query(fn() => Coupon::query()->with('createdBy'))
            ->heading(__('Coupons'))
            ->columns([
                ToggleColumn::make('is_active')
                    ->label(__('Status'))
                    ->onColor('success')
                    ->offColor('danger')
                    ->sortable(),

                TextColumn::make('code')
                    ->label(__('Code'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label(__('Type')),

                TextColumn::make('value')
                    ->label(__('Value'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('max_uses')
                    ->label(__('Max Uses'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('current_uses')
                    ->label(__('Current Uses'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('min_order_amount')
                    ->label(__('Min Order Amount'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('starts_at')
                    ->label(__('Starts At'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('expires_at')
                    ->label(__('Expires At'))
                    ->searchable()
                    ->sortable(),
            ])
            ->filters($this->getTableFilters())
            ->actions(
                $this->getTableActions()
            )
            ->headerActions([
                CreateAction::make('create')
                    ->label(__('Add Coupon'))
                    ->url(route('coupons.add'))
                    ->visible(fn() => auth()->user()->can('Coupon Create'))
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
                ->visible(fn() => auth()->user()->can('Coupon Info')),
            ViewAction::make('view')
                ->form(function (Forms\Form $form, $record) {
                    return $form->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('code')
                                    ->label(__('Code'))
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
                ->visible(fn() => auth()->user()->can('Coupon View')),
            EditAction::make('edit')
                ->url(fn(Coupon $record) => route('coupons.edit', $record->id))
                ->visible(fn() => auth()->user()->can('Coupon Update')),

            DeleteAction::make('delete')
                ->action(fn($record) => $record->forceDelete())
                ->visible(fn() => auth()->user()->can('Coupon Delete')),
        ];
    }

    public function getTableBulkAction(): array
    {
        return [
            BulkActionGroup::make([
                BulkAction::make('delete')
                    ->label(__('Delete'))
                    ->requiresConfirmation()
                    ->action(fn(Collection $records) => $records->each->delete()),
            ])
        ];
    }

    public function render()
    {
        return view('coupon::livewire.pages.coupon.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Categories List'), 'url' => route('categories.index')],
            ]
        ]);
    }
}
