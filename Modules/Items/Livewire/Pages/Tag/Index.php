<?php

namespace Modules\Items\Livewire\Pages\Tag;

use App\View\Components\AppLayout;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Livewire\Component;
use Filament\Forms;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Grid;
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
use Modules\Items\Entities\Tag;
use Modules\Items\Repositories\TagRepository;

class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table)
    {
        return $table
            ->query(fn() => Tag::query()->with('createdBy', 'translations'))
            ->heading(__('Tags'))
            ->emptyStateHeading(__('No tags found'))
            ->emptyStateDescription(__('Create a new tag by clicking the button below'))
            ->emptyStateActions([
                CreateAction::make()
                    ->label(__('Add Tag'))
                    ->visible(fn() => auth()->user()->can('Tag Create'))
            ])
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->color('primary')
                    ->icon('solar-pen-new-square-bold')
                    ->action(
                        EditAction::make('edit')
                            ->visible(fn() => auth()->user()->can('Tag Edit'))
                    ),
                TextColumn::make('slug')
                    ->label(__('Slug'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('color')
                    ->label(__('Color'))
                    ->state(fn ($record) => app(TagRepository::class)->renderTag($record))
                    ->html(),
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
                    ->label(__('Add Tag'))
                    ->modalHeading(__('Add Tag'))
                    ->modalWidth('md')
                    ->form([
                        TextInput::make('name')
                            ->label(__('Name'))
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, ?string $state) {
                                $set('slug', Str::slug($state));
                            })
                            ->required(),
                        TextInput::make('slug')
                            ->required()
                            ->label(__('Slug'))
                            ->unique(Tag::class, 'slug', ignoreRecord: true),
                        Textarea::make('description')
                            ->label(__('Description')),
                        ColorPicker::make('text_color')
                            ->label(__('Text Color'))
                            ->default('#ffffff')
                            ->required(),
                        ColorPicker::make('background_color')
                            ->label(__('Background Color'))
                            ->default('#000000')
                            ->required(),
                    ])
                    ->action(function ($data) {
                        $locale = $data['locale'] = app()->getLocale();
                        $data['created_by'] = auth()->user()->id;
                        $tag = Tag::create($data);

                        Toaster::success(__('Tag created successfully'));
                    })
                    ->visible(fn() => auth()->user()->can('Tag Create'))
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
                ->visible(fn() => auth()->user()->can('Tag Info')),
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
                ->visible(fn() => auth()->user()->can('Tag View')),
            EditAction::make('edit')
                ->label('')
                ->modalHeading(__('Edit Tag'))
                ->modalWidth('md')
                ->form(function ($record) {
                    return [
                        TextInput::make('name')
                            ->label(__('Name'))
                            ->live(onBlur: true)
                            ->default($record->name)
                            ->afterStateUpdated(function (Set $set, ?string $state) {
                                $set('slug', Str::slug($state));
                            })
                            ->required(),
                        TextInput::make('slug')
                            ->label(__('Slug'))
                            ->required()
                            ->default($record->slug)
                            ->unique(Tag::class, 'slug', ignoreRecord: true),
                        Textarea::make('description')
                            ->label(__('Description'))
                            ->default($record->description),
                        ColorPicker::make('text_color')
                            ->label(__('Text Color'))
                            ->default($record->text_color)
                            ->required(),
                        ColorPicker::make('background_color')
                            ->label(__('Background Color'))
                            ->default($record->background_color)
                            ->required(),
                    ];
                })
                ->mutateFormDataUsing(function (array $data): array {
                    $data['locale'] = app()->getLocale();
                    return $data;
                })
                ->visible(fn() => auth()->user()->can('Tag Update'))
                ->action(function (array $data, $record) {
                    $record->update($data);

                    Toaster::success(__('Tag updated successfully'));
                }),

            DeleteAction::make('delete')
                ->action(function ($record) {
                    $record->forceDelete();

                    Toaster::success(__('Tag deleted successfully'));
                })
                ->visible(fn() => auth()->user()->can('Tag Delete')),
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
                    ->visible(fn() => auth()->user()->can('Tag Delete')),
            ])
        ];
    }

    public function render()
    {
        return view('items::livewire.pages.tag.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Categories List'), 'url' => route('categories.index')],
            ]
        ]);
    }
}
