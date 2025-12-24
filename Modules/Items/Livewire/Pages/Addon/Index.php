<?php

namespace Modules\Items\Livewire\Pages\Addon;

use App\View\Components\AppLayout;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Livewire\Component;
use Filament\Forms;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Set;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Jantinnerezo\LivewireAlert\LivewireAlert;
use Masmerise\Toaster\Toaster;
use Modules\Core\Filament\Forms\Components\CreateAction;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\Items\Entities\Addon;
use Modules\Items\Entities\Brand;
use Modules\Items\Enums\AddonType;

class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table)
    {
        return $table
            ->query(fn() => Addon::query()->with('media', 'createdBy', 'translations'))
            ->heading(__('Addons'))
            ->emptyStateHeading(__('No addons found'))
            ->emptyStateDescription(__('Create a new addon by clicking the button below'))
            ->emptyStateActions([
                CreateAction::make()
                    ->label(__('Add Addon'))
                    ->url(fn() => route('addons.add'))
                    ->visible(fn() => auth()->user()->can('Addon Create'))
            ])
            ->columns([
                ImageColumn::make('image')
                    ->label(__('Image'))
                    ->getStateUsing(function ($record) {
                        return $record->getImageAttribute();
                    }),
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->color('primary')
                    ->icon('solar-pen-new-square-bold')
                    ->searchable(
                        query: function (Builder $query, string $search): Builder {
                            return $query->whereHas('translations', function ($q) use ($search) {
                                $q->where('name', 'like', '%' . $search . '%');
                            });
                        }
                    )
                    ->action(
                        EditAction::make('edit')
                            ->visible(fn() => auth()->user()->can('Addon Edit'))
                    ),
                TextColumn::make('description')
                    ->label(__('Description')),
                TextColumn::make('type')
                    ->label(__('Type'))
                    ->badge()
                    ->color(AddonType::class),
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
                    ->label(__('Add Addon'))
                    ->url(fn() => route('addons.add'))
                    ->visible(fn() => auth()->user()->can('Currency Create'))
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
                ->visible(fn() => auth()->user()->can('Addon Info')),
            ViewAction::make('view')
                ->form(function (Forms\Form $form, $record) {
                    return $form->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('Name'))
                                    ->default($record->name)
                                    ->disabled(),
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
                ->visible(fn() => auth()->user()->can('Addon View')),
            EditAction::make('edit')
                ->label('')
                ->url(fn($record) => route('addons.edit', $record))
                ->visible(fn() => auth()->user()->can('Addon Update')),

            DeleteAction::make('delete')
                ->action(function ($record) {
                    $record->forceDelete();

                    Toaster::success(__('Addon deleted successfully'));
                })
                ->visible(fn() => auth()->user()->can('Addon Delete')),
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
                    ->visible(fn() => auth()->user()->can('Currency Delete')),
            ])
        ];
    }

    public function render()
    {
        return view('items::livewire.pages.addon.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Addons List'), 'url' => route('categories.index')],
            ]
        ]);
    }
}
