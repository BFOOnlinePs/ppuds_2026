<?php

namespace Modules\Content\Livewire\Pages\Banner;

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
use Masmerise\Toaster\Toaster;
use Modules\Branch\Entities\Branch;
use Modules\Content\Entities\Banner;
use Modules\Core\Filament\Forms\Components\CreateAction;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Product;

class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table)
    {
        return $table
            ->query(fn() => Banner::query()->with('createdBy'))
            ->heading(__('Banners'))
            ->emptyStateHeading(__('No banners found'))
            ->emptyStateDescription(__('Create a new banner by clicking the button below')) // مترجمة
            ->emptyStateActions([
                CreateAction::make()
                    ->label(__('Add Banner'))
                    ->visible(fn() => auth()->user()->can('Banner Create'))
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
//                TextColumn::make('url')
//                    ->label(__('Url'))
//                    ->getStateUsing(function ($record) {
//                        return route('categories.index', $record);
//                    }),
                TextColumn::make('bannable_type')
                    ->label(__('Bannable Type')),
                TextColumn::make('bannable_id')
                    ->label(__('Bannable ID')),
                TextColumn::make('active')
                    ->label(__('Status')),
            ])
            ->actions(
                $this->getTableActions()
            )
            ->headerActions([
                CreateAction::make('create')
                    ->label(__('Add Banner'))
                    ->form([
                        Grid::make(1)
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('name')
                                            ->columnSpan(1)
                                            ->label(__('Name'))
                                            ->required(),
                                        Select::make('branch_id')
                                            ->columnSpan(1)
                                            ->searchable()
                                            ->options(Branch::get()->pluck('name', 'id'))
                                            ->label(__('Branch'))
                                            ->required(),
                                        Textarea::make('description')
                                            ->columnSpanFull()
                                            ->label(__('Description')),
                                        Select::make('bannable_model')
                                            ->label(__('Bannable Model'))
                                            ->searchable()
                                            ->options([
                                                'items_category' => __('Category'),
                                                'items_product' => __('Product'),
                                            ])
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(fn($state, Set $set) => $set('bannable_id', null)),
                                        Select::make('bannable_id')
                                            ->label(__('Bannable ID'))
                                            ->searchable()
                                            ->required()
                                            ->options(function (Get $get) {
                                                $modelType = $get('bannable_model');

                                                if ($modelType === 'items_category') {
                                                    return Category::get()->pluck('name', 'id');
                                                }

                                                if ($modelType === 'items_product') {
                                                    return Product::get()->pluck('name', 'id');
                                                }

                                                return [];
                                            })
                                            ->hidden(fn(Get $get) => !$get('bannable_model')),
                                        SpatieMediaLibraryFileUpload::make('image')
                                            ->required()
                                            ->image()
                                            ->disk('banners')
                                            ->collection('banner')
                                            ->label(__('Image'))
                                            ->imageResizeMode('cover')
                                            ->imageCropAspectRatio('1:1')
                                            ->imageResizeTargetWidth('400')
                                            ->imageResizeTargetHeight('400')
                                            ->maxSize(10000),
                                    ])
                            ])
                    ])
                    ->using(function ($data, CreateAction $action) {
                        $this->authorize('Banner Create');
                        $modelType = $data['bannable_model'];
                        $modelClassMap = [
                            'items_category' => Category::class,
                            'items_product' => Product::class,
                        ];
                        $bannableTypeClass = $modelClassMap[$modelType] ?? null;
                        if (!$bannableTypeClass) {
                            return;
                        }
                        $data['bannable_type'] = $bannableTypeClass;
                        $data['created_by'] = auth()->id();
                        unset($data['bannable_model']);
                        $banner = Banner::create($data);
                        if (isset($this->data['image'])) {
                            $banner->addImage($this->data['image']);
                        }
                        return $banner;
                    })
                    ->after(function () {
                        Toaster::success(__('Banner created successfully'));
                    })
                    ->visible(fn() => auth()->user()->can('Banner Create')),
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
                ->visible(fn() => auth()->user()->can('Banner Info')),
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
                ->visible(fn() => auth()->user()->can('Banner View')),

            EditAction::make('edit')
                ->label('')
                ->form(function (Form $form, $record) {
                    return $form->schema([
                        Grid::make(1)
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('name')
                                            ->columnSpan(1)
                                            ->label(__('Name'))
                                            ->default($record->name)
                                            ->required(),
                                        Select::make('branch_id')
                                            ->columnSpan(1)
                                            ->default($record->branch_id)
                                            ->searchable()
                                            ->options(Branch::get()->pluck('name', 'id'))
                                            ->label(__('Branch'))
                                            ->required(),
                                        Textarea::make('description')
                                            ->columnSpanFull()
                                            ->label(__('Description'))
                                            ->default($record->description)
                                            ->required(),
                                        Select::make('bannable_model')
                                            ->label(__('Bannable Model'))
                                            ->default($record->bannable_model)
                                            ->columnSpan(1)
                                            ->searchable()
                                            ->options([
                                                'items_category' => __('Category'),
                                                'items_product' => __('Product'),
                                            ])
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(fn($state, Set $set) => $set('bannable_id', null)),
                                        Select::make('bannable_id')
                                            ->columnSpan(1)
                                            ->label(__('Bannable ID'))
                                            ->searchable()
                                            ->default($record->bannable_id)
                                            ->required()
                                            ->options(function (Get $get) {
                                                $modelType = $get('bannable_model');

                                                if ($modelType === 'items_category') {
                                                    return Category::get()->pluck('name', 'id');
                                                }

                                                if ($modelType === 'items_product') {
                                                    return Product::get()->pluck('name', 'id');
                                                }

                                                return [];
                                            })
                                            ->hidden(fn(Get $get) => !$get('bannable_model')),
                                        \Filament\Forms\Components\Placeholder::make('current_image')
                                            ->columnSpanFull()
                                            ->label('')
                                            ->content(fn() => view('core::components.image', ['url' => $record->getImageAttribute()])),

                                        SpatieMediaLibraryFileUpload::make('image')
                                            ->columnSpanFull()
                                            ->label(__('Image'))
                                            ->image()
                                            ->disk('banners')
                                            ->collection('banner')
                                            ->maxSize(2048)
                                            ->imageResizeMode('cover')
                                            ->imageCropAspectRatio('1:1')
                                            ->imageResizeTargetWidth('400')
                                            ->imageResizeTargetHeight('400'),
                                    ])
                            ])
                    ]);
                })
                ->mutateFormDataUsing(function (array $data): array {
                    $data['locale'] = app()->getLocale();

                    $modelType = $data['bannable_model'] ?? null;
                    if ($modelType) {
                        $modelClassMap = [
                            'items_category' => Category::class,
                            'items_product' => Product::class,
                        ];
                        $bannableTypeClass = $modelClassMap[$modelType] ?? null;

                        if ($bannableTypeClass) {
                            $data['bannable_type'] = $bannableTypeClass;
                        }
                    }
                    unset($data['bannable_model']); // احذف الحقل المؤقت

                    return $data;
                })
                ->action(function ($data, $record) {
                    $this->authorize('Banner Update');

                    if (array_key_exists('image', $data) && is_null($data['image'])) {
                        unset($data['image']);
                    }

                    $record->update($data);

                    Toaster::success(__('banner updated successfully')); // مترجمة
                })
                ->visible(fn() => auth()->user()->can('Banner Update')),

            DeleteAction::make('delete')
                ->action(function ($record) {
                    $this->authorize('Banner Delete');
                    $record->delete();
                    Toaster::success(__('banner deleted successfully')); // مترجمة
                })
                ->visible(fn() => auth()->user()->can('Banner Delete')),
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
                    ->visible(fn() => auth()->user()->can('Banner Delete')),
            ])
        ];
    }

    public function render()
    {
        return view('content::livewire.pages.banner.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Banner List'), 'url' => route('banners.index')],
            ]
        ]);
    }
}
