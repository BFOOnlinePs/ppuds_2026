<?php

namespace Modules\Items\Livewire\Pages\Product;

use App\View\Components\AppLayout;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Livewire\Component;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use GuzzleHttp\Promise\Create;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rules\Can;
use Masmerise\Toaster\Toaster;
use Milon\Barcode\DNS1D;
use Modules\Core\Entities\User;
use Modules\Core\Filament\Forms\Components\ActivityLogAction;
use Modules\Core\Filament\Forms\Components\CreateAction;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\ProductActive;
use Modules\Items\Services\ItemsPdfService;


class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table)
    {
        return $table
            ->query(fn() => Product::query()->with('createdBy', 'translations')->where('parent_id', null))
            ->heading(__('Products'))
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
                        return route('products.edit', $record);
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
                TextColumn::make('Category')
                    ->label(__('Category'))
                    ->getStateUsing(function ($record) {
                        return $record->categories->pluck('name')->join(', ');
                    })
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->label(__('Active'))
                    ->afterStateUpdated(function ($state, $record) {
                        // dd($state);
                        $record->update([
                            'is_active' => $state
                                ? ProductActive::IS_ACTIVE->value
                                : ProductActive::IN_ACTIVE->value
                        ]);

                        Toaster::success('Product ' . ($state ? 'activated' : 'deactivated') . ' successfully');
                    })
                    ->sortable(),
                TextColumn::make('sale_price')
                    ->label(__('Sale Price'))

                    ->sortable(),
                TextColumn::make('discount')
                    ->label(__('Discount'))
                    ->sortable(),
                TextColumn::make('locale')
                    ->label(__('Locale'))
                    ->getStateUsing(function ($record) {
                        return $record->translations->pluck('locale')->join(', ');
                    })
                    ->sortable(),
            ])
            ->filters(
                filters: $this->getTableFilters(),
                layout: FiltersLayout::AboveContent,
            )->actions(
                $this->getTableActions()
            )
            ->headerActions([
                CreateAction::make('create')
                    ->label(__('Add Product'))
                    ->url(route('products.add'))
                    ->visible(fn() => auth()->user()->can('Product Create'))
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
            Filter::make('category_id')
                ->form([
                    Select::make('category_id')
                        ->label(__('Category'))
                        ->options(Category::all()->pluck('name', 'id'))
                ])
                ->query(function (Builder $query, array $data) {
                    if (!empty($data['category_id'])) {
                        $query->whereHas('categories', function ($q) use ($data) {
                            $q->where((new Category)->getTable() . '.id', $data['category_id']);
                        });
                    }
                }),
            Filter::make('is_active')
                ->form([
                    Select::make('is_active')
                        ->label(__('Active'))
                        ->options(
                            collect(ProductActive::cases())
                                ->mapWithKeys(fn($case) => [$case->value => $case->label()])
                                ->toArray()
                        ),
                ])
                ->query(function ($query, array $data) {
                    if (isset($data['is_active'])) {
                        $query->where('is_active', $data['is_active']);
                    }
                })
        ];
    }

    protected function getTableActions(): array
    {
        return [
            // ActivityLogAction::make('activity_log')->label(__('Activity Log')),
            Action::make('barcode')
                ->label('')
                ->icon('solar-qr-code-bold')
                ->tooltip(__('Generate Barcode'))
                ->size('lg')
                ->form(function ($record) {
                    $fields = [];

                    $products = Product::where('id', $record->id)->get();

                    foreach ($products as $product) {
                        $fields[] = Grid::make(3)->schema([
                            Placeholder::make('barcode_' . $product->id)
                                ->view('core::components.barcode', ['product' => $product ?? ''])
                                ->label('Barcode')
                                ->columnSpan(2),

                            TextInput::make('quantity_' . $product->id)
                                ->label(__('Quantity'))
                                ->numeric()
                                ->default(1)
                                ->columnSpan(1),
                        ]);

                        foreach ($product->variations as $variation) {
                            // $barcodeHtml = $pdfService->generateBarcode($variation->barcode ?? $variation->id);
                            $fields[] = Grid::make(3)->schema([
                                Placeholder::make('barcode_' . $variation->id)
                                    ->view('core::components.barcode', ['product' => $variation ?? ''])
                                    ->label('Barcode')
                                    ->columnSpan(2),

                                TextInput::make('quantity_' . $variation->id)
                                    ->label(__('Quantity'))
                                    ->numeric()
                                    ->default(1)
                                    ->columnSpan(1),
                            ]);
                        }
                    }


                    return [
                        Grid::make(1)->schema($fields)
                    ];
                })
                ->action(function ($record, array $data) {
                    return app(ItemsPdfService::class)->generateBarcodeLabels($record, $data);
                }),
            InfoAction::make('info')
                ->label('')
                ->visible(fn() => auth()->user()->can('Product Info')),
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
                ->visible(fn() => auth()->user()->can('Product View')),
            Action::make('branch_pricings')
                ->label('')
                ->icon('solar-dollar-minimalistic-bold')
                ->size('lg')
                ->tooltip(__('Branch Pricings'))
                ->url(fn(Product $record) => route('products.branch-pricings', $record->id)),
            EditAction::make('edit')
                ->url(fn(Product $record) => route('products.edit', $record->id))
                ->visible(fn() => auth()->user()->can('Product Update')),

            DeleteAction::make('delete')
                ->action(fn($record) => $record->forceDelete())
                ->visible(fn() => auth()->user()->can('Product Delete')),
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
        return view('items::livewire.pages.product.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Products List'), 'url' => route('products.index')],
            ]
        ]);
    }
}
