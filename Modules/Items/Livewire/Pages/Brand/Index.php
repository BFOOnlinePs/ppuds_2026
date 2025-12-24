<?php

namespace Modules\Items\Livewire\Pages\Brand;

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
use Modules\Items\Entities\Brand;

class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table)
    {
        return $table
            ->query(fn() => Brand::query()->with('media', 'createdBy', 'translations'))
            ->heading(__('Brands'))
            ->emptyStateHeading(__('No brands found'))
            ->emptyStateDescription(__('Create a new brand by clicking the button below'))
            ->emptyStateActions([
                CreateAction::make()
                    ->label(__('Add Currency'))
                    ->visible(fn() => auth()->user()->can('Currency Create'))
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
                    ->action(
                        EditAction::make('edit')
                            ->visible(fn() => auth()->user()->can('Currency Edit'))
                    ),
                TextColumn::make('slug')
                    ->label(__('Slug'))
                    ->searchable()
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
                    ->label(__('Add Currency'))
                    ->modalHeading(__('Add Currency'))
                    ->modalWidth('md')
                    ->model(Brand::class)
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
                            ->unique(Brand::class, 'slug', ignoreRecord: true),
                        Textarea::make('description')
                            ->label(__('Description')),
                        SpatieMediaLibraryFileUpload::make('image')
                            ->required()
                            ->image()
                            ->disk('items')
                            ->directory('brands')
                            ->collection('brand')
                            ->label(__('Image'))
                            ->imageResizeMode('cover')
                            ->imageCropAspectRatio('1:1')
                            ->imageResizeTargetWidth('400')
                            ->imageResizeTargetHeight('400')
                            ->maxSize(2048),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['locale'] = app()->getLocale();
                        $data['created_by'] = auth()->id();
                        return $data;
                    })
                    ->action(function ($form, array $data) {

                        $imageComponent = collect($form->getFlatComponents())
                            ->first(fn($component) => $component->getName() === 'image');

                        $imageFile = $imageComponent?->getState();

                        $brand = Brand::create($data);

                        if (isset($imageFile)) {
                            $brand->addImage($imageFile);
                        }

                        Toaster::success(__('Currency created successfully'));
                    })
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
                ->visible(fn() => auth()->user()->can('Currency Info')),
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
                ->visible(fn() => auth()->user()->can('Currency View')),
            EditAction::make('edit')
                ->label('')
                ->modalHeading(__('Edit Currency'))
                ->modalWidth('md')
                ->form(function ($record) {
                    $currentImageUrl = $record->getFirstMediaUrl('brand');
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
                            ->unique(Brand::class, 'slug', ignoreRecord: true),
                        Textarea::make('description')
                            ->label(__('Description'))
                            ->default($record->description),
                        \Filament\Forms\Components\Placeholder::make('current_image')
                            ->label('')
                            ->content(fn() => view('core::components.image', ['url' => $record->getFirstMediaUrl('brand')])),

                        SpatieMediaLibraryFileUpload::make('image')
                            ->label(__('Image'))
                            ->image()
                            ->disk('items')
                            ->collection('brand')
                            ->directory('brands')
                            ->maxSize(2048)
                            ->imageResizeMode('cover')
                            ->imageCropAspectRatio('1:1')
                            ->imageResizeTargetWidth('400')
                            ->imageResizeTargetHeight('400'),
                    ];
                })
                ->mutateFormDataUsing(function (array $data): array {
                    $data['locale'] = app()->getLocale();
                    return $data;
                })
                ->visible(fn() => auth()->user()->can('Currency Update'))
                ->action(function ($form, array $data, $record) {

                    $imageComponent = collect($form->getFlatComponents())
                        ->first(fn($component) => $component->getName() === 'image');

                    $imageFile = $imageComponent?->getState();

                    $record->update($data);

                    if (isset($imageFile)) {
                        $record->addImage($imageFile);
                    }

                    Toaster::success(__('Currency updated successfully'));
                }),

            DeleteAction::make('delete')
                ->action(function ($record) {
                    $record->forceDelete();

                    Toaster::success(__('Currency deleted successfully'));
                })
                ->visible(fn() => auth()->user()->can('Currency Delete')),
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
        return view('items::livewire.pages.brand.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Brands List'), 'url' => route('categories.index')],
            ]
        ]);
    }
}
