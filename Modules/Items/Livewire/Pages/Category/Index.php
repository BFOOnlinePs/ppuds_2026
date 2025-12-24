<?php

namespace Modules\Items\Livewire\Pages\Category;

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
use Modules\Core\Filament\Forms\Components\ActivityLogAction;
use Modules\Core\Filament\Forms\Components\CreateAction;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\Items\Entities\Category;
use View;

class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table)
    {
        return $table
            ->query(fn() => Category::query()->with('createdBy', 'translations', 'parent'))
            ->heading(__('Categories'))
            ->columns([
                ImageColumn::make('image')
                    ->label(__('Image'))
                    ->getStateUsing(function ($record) {
                        return $record->getImageAttribute();
                    })
                    ->size(60),
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->color('primary')
                    ->icon('solar-pen-new-square-bold')
                    ->url(function ($record) {
                        return route('categories.edit', $record);
                    })
                    ->searchable(
                        query: function (Builder $query, string $search): Builder {
                            return $query->whereHas('translations', function ($q) use ($search) {
                                $q->where('name', 'like', '%' . $search . '%');
                            });
                        }
                    )
                    ->sortable(),
                TextColumn::make('slug')
                    ->label(__('Slug'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('parent')
                    ->label(__('Parent Category'))
                    ->getStateUsing(function ($record) {
                        return $record->parent ? $record->parent->name : __('Parent');
                    })
                    ->sortable(),
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
                    ->label(__('Add Category'))
                    ->url(route('categories.add'))
                    ->visible(fn() => auth()->user()->can('Category Create'))
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
                ->visible(fn() => auth()->user()->can('Category Info')),
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
                ->visible(fn() => auth()->user()->can('Category View')),
            EditAction::make('edit')
                ->url(fn(Category $record) => route('categories.edit', $record->id))
                ->visible(fn() => auth()->user()->can('Category Update')),

            DeleteAction::make('delete')
                ->action(fn($record) => $record->forceDelete())
                ->visible(fn() => auth()->user()->can('Category Delete')),
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
        return view('items::livewire.pages.category.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Categories List'), 'url' => route('categories.index')],
            ]
        ]);
    }
}
