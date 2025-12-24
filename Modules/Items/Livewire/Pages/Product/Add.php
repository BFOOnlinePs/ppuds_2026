<?php

namespace Modules\Items\Livewire\Pages\Product;

use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Actions;
use App\View\Components\AppLayout;
use Beholdr\FilamentTrilist\Components\TrilistSelect;
use Closure;
use CodeWithDennis\FilamentSelectTree\SelectTree;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Hash;
use Illuminate\Support\Str;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Livewire\Component;
use Modules\Branch\Entities\Branch;
use Modules\Core\Entities\User;
use Modules\Core\Filament\Forms\Components\CategoryTreeSelect;
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\Core\Forms\Components\CheckboxTree;
use Modules\Items\Entities\Addon;
use Modules\Items\Entities\Attribute;
use Modules\Items\Entities\AttributeValue;
use Modules\Items\Entities\Brand;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Label;
use Modules\Items\Entities\Product;
use Modules\Items\Entities\Tag;
use Modules\Items\Enums\CategoryStatus;
use Modules\Items\Events\ProductUpdated;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

class Add extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    public function mount()
    {
        $this->form->fill([
            'categories' => Category::query()->where('status', CategoryStatus::ACTIVE)->get()->pluck('id')->toArray(),
        ]);
    }
    public function form(Form $form): Form
    {
        return $form
            ->model(Product::class)
            ->schema([
                Grid::make(3)
                    ->schema([
                        Grid::make()
                            ->columnSpan(3)
                            ->schema([
                                Section::make(__('General information'))
                                    ->columnSpan(2)
                                    ->schema([
                                        Grid::make(1)
                                            ->schema([
                                                Grid::make(2)
                                                    ->schema([
                                                        TextInput::make('name')
                                                            ->required()
                                                            ->label(__('Name'))
                                                            ->live(onBlur: true)
                                                            ->afterStateUpdated(function (Set $set, ?string $state, Get $get) {
                                                                $set('slug', Str::slug($state));
                                                                // تحديث أسماء الـ variations عند تغيير اسم المنتج
                                                                $this->updateVariationsWithParentData($set, $get);
                                                            }),
                                                        TextInput::make('slug')
                                                            ->required()
                                                            ->label(__('Slug'))
                                                            ->unique(Category::class, 'slug', ignoreRecord: true),
                                                        TagsInput::make('tags')
                                                            ->label(__('Tags'))
                                                            ->separator(',')
                                                            ->suggestions(Tag::all()->pluck('name')->toArray()),
                                                        TagsInput::make('labels')
                                                            ->label(__('Labels'))
                                                            ->separator(',')
                                                            ->suggestions(Label::all()->pluck('name')->toArray()),
//                                                        SelectTree::make('category_id')
//                                                            ->label(__('Category'))
//                                                            ->columns(1)
//                                                            ->independent(false)
//                                                            ->enableBranchNode()
//                                                            ->relationship('categories', 'name', 'parent_id')
//                                                            ->required(),
                                                        Select::make('category_id')
                                                            ->label(__('Category'))
                                                            ->options(Category::all()->pluck('name', 'id'))
                                                            ->searchable()
                                                            ->multiple()
                                                            ->required(),
                                                        Select::make('brand_id')
                                                            ->label(__('Brand'))
                                                            ->columns(1)
                                                            ->searchable()
                                                            ->options(Brand::all()->pluck('name', 'id')),
                                                        Select::make('branches')
                                                            ->label(__('Branches'))
                                                            ->relationship('branches', 'name')
                                                            ->columnSpanFull()
                                                            ->multiple()
                                                            ->searchable()
                                                            ->options(Branch::get()->pluck('name', 'id')),
                                                    ]),
                                                Grid::make(1)
                                                    ->schema([
                                                        Textarea::make('short_description')
                                                            ->label(__('Short Description'))
                                                            ->live(onBlur: true)
                                                            ->afterStateUpdated(fn(Set $set, Get $get) => $this->updateVariationsWithParentData($set, $get)),
                                                    ]),
                                                Grid::make(1)
                                                    ->schema([
                                                        RichEditor::make('description')
                                                            ->label(__('Description'))
                                                            ->live(onBlur: true)
                                                            ->afterStateUpdated(fn(Set $set, Get $get) => $this->updateVariationsWithParentData($set, $get)),
                                                    ]),
                                            ]),
                                    ]),
                                Tabs::make('tabs')
                                    ->columnSpanFull()
                                    ->tabs(
                                        [
                                            ...$this->getGeneralTabsSchema(),
                                            ...$this->getPriceSchema(),
                                            ...$this->getInventorySchema(),
                                            ...$this->getAddonsSchema(),

                                            Tabs\Tab::make('variations')
                                                ->label(__('Generate Variations'))
                                                ->schema([
                                                    Actions::make([
                                                        FormAction::make('inherit_parent_data')
                                                            ->label(__('Inherit All Parent Data to Variations'))
                                                            ->icon('heroicon-o-arrow-down')
                                                            ->color('primary')
                                                            ->action(function (Set $set, Get $get) {
                                                                $this->inheritAllParentDataToVariations($set, $get);
                                                            })
                                                            ->visible(fn(Get $get) => !empty(array_filter($get('attributes') ?? [])))
                                                    ])->fullWidth(),

                                                    Grid::make('')
                                                        ->schema(function () {
                                                            return Attribute::with('attributeValues')->get()->map(function ($attribute) {
                                                                return Fieldset::make($attribute->name)
                                                                    ->schema([
                                                                        CheckboxList::make('attributes.' . $attribute->id)
                                                                            ->label('')
                                                                            // ->label($attribute->name)
                                                                            ->options($attribute->attributeValues->pluck('name', 'id'))
                                                                            ->columns(12)
                                                                            ->columnSpanFull()
                                                                            ->reactive()
                                                                            ->afterStateUpdated(
                                                                                fn(Set $set, Get $get) =>
                                                                                $set('variations', $this->generateVariationsFromAttributes(
                                                                                    $get('attributes'),
                                                                                    $get('name'),
                                                                                    $get() // تمرير جميع بيانات النموذج
                                                                                ))
                                                                            ),
                                                                    ])
                                                                    ->columnSpanFull();
                                                            })->toArray();
                                                        }),

                                                    Repeater::make('variations')
                                                        ->label(__('Product Variations'))
                                                        ->collapsed()
                                                        ->visible(fn(Get $get) => !empty(array_filter($get('attributes') ?? [])))
                                                        ->itemLabel(fn(array $state, Get $get): string => ($state['name'] ?? ''))
                                                        ->disableItemCreation()
                                                        ->schema([
                                                            // إضافة Hidden field لحفظ معرفات الخصائص
                                                            Hidden::make('attribute_values')
                                                                ->default(fn($state) => $state['attributes'] ?? []),

                                                            Section::make(__('General Information'))
                                                                ->columnSpanFull()
                                                                ->schema([
                                                                    Grid::make(2)
                                                                        ->schema([
                                                                            TextInput::make('name')
                                                                                ->label(__('Name'))
                                                                                ->reactive()
                                                                                ->disabled()
                                                                                ->required(),
                                                                            TextInput::make('slug')
                                                                                ->label(__('Slug'))
                                                                                ->disabled()
                                                                                ->required()
                                                                                ->unique(Product::class, 'slug', ignoreRecord: true),
                                                                        ]),
                                                                    Textarea::make('short_description')
                                                                        ->label(__('Short Description')),
                                                                    RichEditor::make('description')
                                                                        ->label(__('Description')),
                                                                ]),

                                                            // تبويبات كاملة لكل variation
                                                            Tabs::make('variation_tabs')
                                                                ->columnSpanFull()
                                                                ->tabs([
                                                                    // التبويب العام
                                                                    Tabs\Tab::make('general')
                                                                        ->label(__('General'))
                                                                        ->schema([
                                                                            Grid::make(2)
                                                                                ->schema([
                                                                                    TextInput::make('sku')
                                                                                        ->label(__('Sku'))
                                                                                        ->placeholder(__('Keep empty for auto generated')),
                                                                                    TextInput::make('barcode')
                                                                                        ->label(__('Barcode')),
                                                                                    TextInput::make('weight')
                                                                                        ->label(__('Weight')),
                                                                                    Radio::make('is_featured')
                                                                                        ->label(__('Is this product featured'))
                                                                                        ->options([
                                                                                            '0' => __('No'),
                                                                                            '1' => __('Yes'),
                                                                                        ])
                                                                                        ->default('0')
                                                                                        ->required(),

                                                                                    Radio::make('is_digital')
                                                                                        ->label(__('Is this product digital'))
                                                                                        ->options([
                                                                                            '0' => __('No'),
                                                                                            '1' => __('Yes'),
                                                                                        ])
                                                                                        ->default('0')
                                                                                        ->required(),
                                                                                ])
                                                                        ]),

                                                                    // تبويب الأسعار
                                                                    Tabs\Tab::make('price')
                                                                        ->label(__('Price'))
                                                                        ->schema([
                                                                            Grid::make(2)
                                                                                ->schema([
                                                                                    Fieldset::make(__('Price'))
                                                                                        ->schema([
                                                                                            TextInput::make('base_price')
                                                                                                ->label(__('Base Price'))
                                                                                                ->required(),
                                                                                            TextInput::make('sale_price')
                                                                                                ->label(__('Sale Price')),
                                                                                            TextInput::make('cost_price')
                                                                                                ->label(__('Cost Price')),
                                                                                            TextInput::make('wholesale_price')
                                                                                                ->label(__('Wholesale Price')),
                                                                                        ]),
                                                                                    Fieldset::make(__('Discount'))
                                                                                        ->schema([
                                                                                            Select::make('discount_type')
                                                                                                ->options([
                                                                                                    'percentage' => __('Percentage'),
                                                                                                    'fixed' => __('Fixed'),
                                                                                                ]),
                                                                                            TextInput::make('discount_amount')
                                                                                                ->label(__('Discount Amount')),
                                                                                            DatePicker::make('discount_start_date')
                                                                                                ->label(__('Discount Start Date')),
                                                                                            DatePicker::make('discount_end_date')
                                                                                                ->label(__('Discount End Date')),
                                                                                        ]),
                                                                                ])
                                                                        ]),

                                                                    // تبويب المخزون
                                                                    Tabs\Tab::make('inventory')
                                                                        ->label(__('Inventory'))
                                                                        ->schema([
                                                                            Grid::make(2)
                                                                                ->schema([
                                                                                    Toggle::make('manage_stock')
                                                                                        ->label(__('Manage Stock'))
                                                                                        ->columnSpan(2)
                                                                                        ->live(),
                                                                                    TextInput::make('stock_quantity')
                                                                                        ->label(__('Stock Quantity'))
                                                                                        ->required()
                                                                                        ->visible(fn(Get $get): bool => $get('manage_stock')),
                                                                                    TextInput::make('low_stock_threshold')
                                                                                        ->label(__('Low Stock Threshold'))
                                                                                        ->visible(fn(Get $get): bool => $get('manage_stock')),
                                                                                    Select::make('stock_status')
                                                                                        ->label(__('Stock Status'))
                                                                                        ->options([
                                                                                            'in_stock' => __('In Stock'),
                                                                                            'out_of_stock' => __('Out Of Stock'),
                                                                                            'on_backorder' => __('On Backorder'),
                                                                                        ])
                                                                                        ->visible(fn(Get $get): bool => $get('manage_stock')),
                                                                                    Toggle::make('allow_backorder')
                                                                                        ->label(__('Allow Backorder'))
                                                                                        ->visible(fn(Get $get): bool => $get('manage_stock')),
                                                                                ])
                                                                        ]),

                                                                    // تبويب الصور
                                                                    Tabs\Tab::make('images')
                                                                        ->label(__('Images'))
                                                                        ->schema([
                                                                            Grid::make(2)
                                                                                ->schema([
                                                                                    Section::make(__('Main Image'))
                                                                                        ->schema([
                                                                                            SpatieMediaLibraryFileUpload::make('image')
                                                                                                ->image()
                                                                                                ->disk('items')
                                                                                                ->collection('product')
                                                                                                ->label(__('Image'))
                                                                                                ->imageResizeMode('cover')
                                                                                                ->imageCropAspectRatio('1:1')
                                                                                                ->imageResizeTargetWidth('400')
                                                                                                ->imageResizeTargetHeight('400')
                                                                                                ->maxSize(2048),
                                                                                        ]),
                                                                                    Section::make(__('Gallery'))
                                                                                        ->schema([
                                                                                            SpatieMediaLibraryFileUpload::make('gallery')
                                                                                                ->image()
                                                                                                ->disk('items')
                                                                                                ->collection('product_gallery')
                                                                                                ->label(__('Gallery'))
                                                                                                ->imageResizeMode('cover')
                                                                                                ->imageCropAspectRatio('1:1')
                                                                                                ->reorderable()
                                                                                                ->downloadable()
                                                                                                ->imageResizeTargetWidth('400')
                                                                                                ->imageResizeTargetHeight('400')
                                                                                                ->maxSize(2048)
                                                                                                ->multiple(),
                                                                                        ]),
                                                                                ])
                                                                        ]),

                                                                    // تبويب التصنيفات
                                                                    Tabs\Tab::make('categories')
                                                                        ->label(__('Categories'))
                                                                        ->schema([
//                                                                            SelectTree::make('category_id')
//                                                                                ->label(__('Category'))
//                                                                                ->columnSpanFull()
//                                                                                ->independent(false)
//                                                                                ->enableBranchNode()
//                                                                                ->relationship('categories', 'name', 'parent_id')
//                                                                                ->required()
                                                                        ]),

                                                                    // تبويب المعلومات الوصفية
                                                                    Tabs\Tab::make('meta')
                                                                        ->label(__('Meta Information'))
                                                                        ->schema([
                                                                            TextInput::make('meta_title')
                                                                                ->label(__('Meta Title'))
                                                                                ->maxLength(60),
                                                                            Textarea::make('meta_description')
                                                                                ->label(__('Meta Description'))
                                                                                ->maxLength(160),
                                                                            TextInput::make('meta_keywords')
                                                                                ->label(__('Meta Keywords')),
                                                                        ])
                                                                ])
                                                        ])
                                                        ->columnSpanFull()
                                                        ->defaultItems(0), // إضافة هذا
                                                ])
                                        ]
                                    ),
                            ]),

                        Grid::make()
                            ->columnSpanFull()
                            ->schema([
                                Section::make(__('Images'))
                                    ->collapsed()
                                    ->schema([
                                        SpatieMediaLibraryFileUpload::make('image')
                                            ->required()
                                            ->image()
                                            ->disk('items')
                                            ->collection('product')
                                            ->label(__('Image'))
                                            ->imageResizeMode('cover')
                                            ->imageCropAspectRatio('1:1')
                                            ->imageResizeTargetWidth('400')
                                            ->imageResizeTargetHeight('400')
                                            ->maxSize(5000),

                                        SpatieMediaLibraryFileUpload::make('gallery')
                                            ->image()
                                            ->disk('items')
                                            ->collection('product_gallery')
                                            ->label(__('Gallery'))
                                            ->imageResizeMode('cover')
                                            ->imageCropAspectRatio('1:1')
                                            ->imageResizeTargetWidth('400')
                                            ->imageResizeTargetHeight('400')
                                            ->panelLayout('grid')
                                            ->maxSize(2048)
                                            ->multiple(),
                                    ]),

                                Section::make(__('Meta Information'))
                                    ->columnSpanFull()
                                    ->schema([
                                        TextInput::make('meta_title')
                                            ->label(__('Meta Title'))
                                            ->maxLength(60),
                                        Textarea::make('meta_description')
                                            ->label(__('Meta Description'))
                                            ->maxLength(160),
                                        TextInput::make('meta_keywords')
                                            ->label(__('Meta Keywords')),
                                    ])
                                    ->collapsed(),
                            ])
                    ]),
            ])
            ->statePath('data');
    }

    /**
     * دالة لنسخ جميع بيانات المنتج الرئيسي إلى جميع الـ variations
     */
    public function inheritAllParentDataToVariations(Set $set, Get $get): void
    {
        $variations = $get('variations') ?? [];
        if (empty($variations)) {
            return;
        }

        // الحقول التي نريد نسخها من المنتج الرئيسي
        $parentData = $this->getParentDataForInheritance($get);

        foreach ($variations as $index => $variation) {
            foreach ($parentData as $field => $value) {
                // تجنب الكتابة على الحقول الخاصة بالـ variation
                if (!in_array($field, ['name', 'slug', 'attributes', 'attribute_values'])) {
                    $set("variations.{$index}.{$field}", $value);
                }
            }
        }
    }

    /**
     * دالة لتحديث الـ variations عند تغيير بيانات المنتج الرئيسي
     */
    private function updateVariationsWithParentData(Set $set, Get $get): void
    {
        $variations = $get('variations') ?? [];
        if (empty($variations)) {
            return;
        }

        // تحديث أسماء الـ variations فقط عند تغيير اسم المنتج
        $parentName = $get('name');
        $attributes = $get('attributes') ?? [];

        if ($parentName && !empty(array_filter($attributes))) {
            $updatedVariations = $this->generateVariationsFromAttributes($attributes, $parentName, $get());
            $set('variations', $updatedVariations);
        }
    }

    /**
     * استخراج البيانات المطلوب وراثتها من المنتج الرئيسي
     */
    private function getParentDataForInheritance(Get $get): array
    {
        return [
            // البيانات العامة
            'short_description' => $get('short_description'),
            'description' => $get('description'),
            'barcode' => $get('barcode'),
            'weight' => $get('weight'),
            'is_featured' => $get('is_featured'),
            'is_digital' => $get('is_digital'),

            // بيانات الأسعار
            'base_price' => $get('base_price'),
            'sale_price' => $get('sale_price'),
            'cost_price' => $get('cost_price'),
            'wholesale_price' => $get('wholesale_price'),
            'discount_type' => $get('discount_type'),
            'discount_amount' => $get('discount_amount'),
            'discount_start_date' => $get('discount_start_date'),
            'discount_end_date' => $get('discount_end_date'),

            // بيانات المخزون
            'manage_stock' => $get('manage_stock'),
            'stock_quantity' => $get('stock_quantity'),
            'low_stock_threshold' => $get('low_stock_threshold'),
            'stock_status' => $get('stock_status'),
            'allow_backorder' => $get('allow_backorder'),

            // الصور والتصنيفات
            'image' => $get('image'),
            'gallery' => $get('gallery'),
            'category_id' => $get('category_id'),

            // المعلومات الوصفية
            'meta_title' => $get('meta_title'),
            'meta_description' => $get('meta_description'),
            'meta_keywords' => $get('meta_keywords'),
        ];
    }

    private function getParentDataFromArray(array $data): array
    {
        return [
            'short_description' => $data['short_description'] ?? null,
            'description' => $data['description'] ?? null,
            'barcode' => $data['barcode'] ?? null,
            'weight' => $data['weight'] ?? null,
            'is_featured' => $data['is_featured'] ?? null,
            'is_digital' => $data['is_digital'] ?? null,
            'base_price' => $data['base_price'] ?? null,
            'sale_price' => $data['sale_price'] ?? null,
            'cost_price' => $data['cost_price'] ?? null,
            'wholesale_price' => $data['wholesale_price'] ?? null,
            'discount_type' => $data['discount_type'] ?? null,
            'discount_amount' => $data['discount_amount'] ?? null,
            'discount_start_date' => $data['discount_start_date'] ?? null,
            'discount_end_date' => $data['discount_end_date'] ?? null,
            'manage_stock' => $data['manage_stock'] ?? null,
            'stock_quantity' => $data['stock_quantity'] ?? null,
            'low_stock_threshold' => $data['low_stock_threshold'] ?? null,
            'stock_status' => $data['stock_status'] ?? null,
            'allow_backorder' => $data['allow_backorder'] ?? null,
            'image' => $data['image'] ?? null,
            'gallery' => $data['gallery'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'meta_keywords' => $data['meta_keywords'] ?? null,
        ];
    }

    public function generateVariationsFromAttributes(array $attributes, ?string $parentName, array $parentData = []): array
    {
        $filtered = array_filter($attributes, fn($values) => !empty($values));
        if (empty($filtered)) {
            return [];
        }

        $filtered = array_map(fn($v) => is_array($v) ? $v : [$v], $filtered);
        $allValueIds = collect($filtered)->flatten()->unique()->all();
        $valueNames = AttributeValue::whereIn('id', $allValueIds)
            ->get()->pluck('name', 'id');
        $combinations = $this->cartesianProduct($filtered);

        return collect($combinations)->map(function ($combination) use ($valueNames, $parentName, $parentData) {
            // الأسماء الفرعية للقيم
            $valueLabels = array_map(fn($id) => $valueNames[$id] ?? $id, $combination);

            // ادمج اسم المنتج مع قيم الـvariation
            $fullName = trim(implode(' - ', array_filter([
                $parentName,
                ...$valueLabels,
            ])));

            // البيانات الأساسية للـ variation
            $variationData = [
                'attributes' => $combination,
                'attribute_values' => $combination, // إضافة هذا للحفظ
                'name' => $fullName,
                'slug' => Str::slug($fullName),
            ];

            // إضافة بيانات المنتج الرئيسي إذا كانت متوفرة
            if (!empty($parentData)) {
                $inheritedData = $this->getParentDataFromArray($parentData);
                $variationData = array_merge($variationData, $inheritedData);
            }

            // القيم الافتراضية
            return array_merge([
                'manage_stock' => true,
                'stock_status' => 'in_stock',
                'is_featured' => '0',
                'is_digital' => '0',
            ], $variationData);
        })->toArray();
    }

    private function cartesianProduct($arrays)
    {
        $result = [[]];
        foreach ($arrays as $key => $values) {
            $append = [];
            foreach ($result as $product) {
                foreach ($values as $value) {
                    $productCopy = $product;
                    $productCopy[$key] = $value;
                    $append[] = $productCopy;
                }
            }
            $result = $append;
        }
        return $result;
    }

    public function getGeneralSchema(): array
    {
        return [
            Section::make(__('General information'))
                ->columnSpan(2)
                ->schema([
                    Grid::make(1)->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->required()
                                ->label(__('Name'))
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn(Set $set, ?string $state) => $set('slug', Str::slug($state))),
                            TextInput::make('slug')
                                ->required()
                                ->label(__('Slug'))
                                ->unique(Product::class, 'slug', ignoreRecord: true),
                        ]),

                        Textarea::make('short_description')->label(__('Short Description')),
                        RichEditor::make('description')->label(__('Description')),
                    ]),
                ]),
        ];
    }

    public function getPriceSchema(): array
    {
        return [
            Tabs\Tab::make('price')->label(__('Price'))->schema([
                Grid::make(2)->schema([
                    Fieldset::make(__('Price'))->schema([
                        TextInput::make('base_price')->label(__('Base Price'))->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn(Set $set, Get $get) => $this->updateVariationsWithParentData($set, $get)),
                        TextInput::make('sale_price')->label(__('Sale Price'))
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn(Set $set, Get $get) => $this->updateVariationsWithParentData($set, $get)),
                        TextInput::make('cost_price')->label(__('Cost Price'))
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn(Set $set, Get $get) => $this->updateVariationsWithParentData($set, $get)),
                        TextInput::make('wholesale_price')->label(__('Wholesale Price'))
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn(Set $set, Get $get) => $this->updateVariationsWithParentData($set, $get)),
                    ]),
                    Fieldset::make(__('Discount'))->schema([
                        Select::make('discount_type')->label(__('Discount Type'))->options([
                            'percentage' => __('Percentage'),
                            'fixed' => __('Fixed'),
                        ])
                            ->live()
                            ->afterStateUpdated(fn(Set $set, Get $get) => $this->updateVariationsWithParentData($set, $get)),
                        TextInput::make('discount_amount')->label(__('Discount Amount'))
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn(Set $set, Get $get) => $this->updateVariationsWithParentData($set, $get)),
                        DatePicker::make('discount_start_date')->label(__('Discount Start Date'))
                            ->live()
                            ->afterStateUpdated(fn(Set $set, Get $get) => $this->updateVariationsWithParentData($set, $get)),
                        DatePicker::make('discount_end_date')->label(__('Discount End Date'))
                            ->live()
                            ->afterStateUpdated(fn(Set $set, Get $get) => $this->updateVariationsWithParentData($set, $get)),
                    ]),
                ]),
            ]),
        ];
    }

    public function getGeneralTabsSchema(): array
    {
        return [
            Tabs\Tab::make('general')->label(__('General'))
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextInput::make('sku')->columnSpan(1)->label(__('Sku'))
                                ->live(onBlur: true)
                                ->placeholder(__('Keep empty for auto generated'))
                                ->afterStateUpdated(fn(Set $set, Get $get) => $this->updateVariationsWithParentData($set, $get)),
                            TextInput::make('barcode')->columnSpan(1)->label(__('Barcode'))
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn(Set $set, Get $get) => $this->updateVariationsWithParentData($set, $get)),
                            TextInput::make('weight')->columnSpan(1)->label(__('Weight'))
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn(Set $set, Get $get) => $this->updateVariationsWithParentData($set, $get)),
                            Radio::make('is_featured')
                                ->columnSpan(1)
                                ->label(__('Is this product featured'))
                                ->required()
                                ->options(['0' => __('No'), '1' => __('Yes')])
                                ->default('0')
                                ->live()
                                ->afterStateUpdated(fn(Set $set, Get $get) => $this->updateVariationsWithParentData($set, $get)),
                            Radio::make('is_digital')
                                ->columnSpan(1)
                                ->label(__('Is this product digital'))
                                ->required()
                                ->options(['0' => __('No'), '1' => __('Yes')])
                                ->default('0')
                                ->live()
                                ->afterStateUpdated(fn(Set $set, Get $get) => $this->updateVariationsWithParentData($set, $get)),
                        ])
                ])
        ];
    }

    public function getInventorySchema(): array
    {
        return [
            Tabs\Tab::make('inventory')->label(__('Inventory'))->schema([
                Grid::make(2)->schema([
                    Toggle::make('manage_stock')->label(__('Manage Stock'))->live()
                        ->columnSpan(1)
                        ->afterStateUpdated(fn(Set $set, Get $get) => $this->updateVariationsWithParentData($set, $get)),
                    Toggle::make('allow_backorder')
                        ->label(__('Allow Backorder'))
                        ->columnSpan(1)
                        ->visible(fn(Get $get): bool => $get('manage_stock'))
                        ->live()
                        ->afterStateUpdated(fn(Set $set, Get $get) => $this->updateVariationsWithParentData($set, $get)),

                    TextInput::make('stock_quantity')->label(__('Stock Quantity'))
                        ->visible(fn(Get $get): bool => $get('manage_stock'))
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn(Set $set, Get $get) => $this->updateVariationsWithParentData($set, $get)),
                    TextInput::make('low_stock_threshold')->label(__('Low Stock Threshold'))
                        ->visible(fn(Get $get): bool => $get('manage_stock'))
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn(Set $set, Get $get) => $this->updateVariationsWithParentData($set, $get)),
                    Select::make('stock_status')
                        ->label(__('Stock Status'))
                        ->options([
                            'in_stock' => __('In Stock'),
                            'out_of_stock' => __('Out Of Stock'),
                            'on_backorder' => __('On Backorder'),
                        ])
                        ->visible(fn(Get $get): bool => $get('manage_stock'))
                        ->live()
                        ->afterStateUpdated(fn(Set $set, Get $get) => $this->updateVariationsWithParentData($set, $get)),
                ])
            ])
        ];
    }

    public function getAddonsSchema(): array
    {
        return [
            Tabs\Tab::make('addons')
                ->label(__('Addons'))
                ->schema([
                    Repeater::make('addons')
                        ->label(__('Link Addons to this Product'))
                        ->collapsible()
                        ->collapsed()
                        ->itemLabel(fn (array $state): ?string => Addon::find($state['addon_id'])?->name ?? __('New Option'))
                        ->columnSpanFull()
                        ->schema([
                            Select::make('addon_id')
                                ->label(__('Addon Group'))
                                ->options(Addon::all()->pluck('name', 'id'))
                                ->live() // مهم جداً
                                ->searchable()
                                ->required()
                                ->distinct()
                                ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                ->columnSpan(2),

                            Toggle::make('is_required')
                                ->label(__('Is Required?'))
                                ->default(false)
                                ->columnSpan(1),

                            TextInput::make('min_selection')
                                ->label(__('Min Selection'))
                                ->numeric()
                                ->default(1)
                                ->columnSpan(1),

                            TextInput::make('max_selection')
                                ->label(__('Max Selection'))
                                ->placeholder(__('Leave empty for no limit'))
                                ->numeric()
                                ->columnSpan(1),

                            TextInput::make('max_quantity_per_option')
                                ->label(__('Max Qty Per Option'))
                                ->numeric()
                                ->default(1)
                                ->columnSpan(1),

                            // ---== بداية التغيير: استبدلنا CheckboxList بـ Repeater ==---
                            Repeater::make('allowed_options') // <-- كان CheckboxList
                            ->label(__('Allowed Options & Custom Prices'))
                                ->columnSpanFull()
                                ->visible(fn (Get $get) => filled($get('addon_id')))
                                ->helperText(__('أضف الخيارات المسموحة. اترك السعر فارغاً لاستخدام السعر الافتراضي.'))
                                ->columns(3) // عمودين: واحد للـ Select وواحد للسعر
                                ->schema([
                                    Select::make('addon_option_id')
                                        ->label(__('Option'))
                                        ->required()
                                        ->distinct() // يمنع اختيار نفس الخيار مرتين
                                        ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                        ->searchable()
                                        ->options(function (Get $get) {
                                            // $get('../../addon_id') للوصول للحقل في الريبيتر الأب
                                            $addonId = $get('../../addon_id');
                                            if (!$addonId) {
                                                return [];
                                            }

                                            $addon = Addon::with('addonOptions')->find($addonId);
                                            if (!$addon) {
                                                return [];
                                            }

                                            // يعرض اسم الخيار وسعره الافتراضي كتلميح
                                            return $addon->addonOptions->mapWithKeys(function ($option) {
                                                return [$option->id => $option->name . ' (' . __('Default:') . ' ' . $option->price . ')'];
                                            });
                                        }),

                                    TextInput::make('price')
                                        ->label(__('Override Price'))
                                        ->numeric()
                                        ->nullable() // <-- مهم
                                        ->placeholder(__('Use default price')),

                                    Select::make('is_default')
                                        ->label(__('Is Default'))
                                        ->options(function (){
                                            return [
                                                0 => __('No'),
                                                1 => __('Yes'),
                                            ];
                                        })
                                ])
                                ->defaultItems(0)
                            // ---== نهاية التغيير ==---
                        ])
                        ->columns(6)
                ])
        ];
    }

    public function rules(): array
    {
        return [
            'data.name' => ['required', 'string', 'max:255'],
            'data.slug' => ['required', 'string', 'max:255'],
            'data.image' => [
                'required',
                'image',
                'mimes:jpeg,jpg,png,gif,webp',
                'max:2048',
            ]
        ];
    }

    public function messages(): array
    {
        return [
            'data.name.required' => __('Name is required'),
            'data.slug.required' => __('Slug is required'),
            'data.image.required' => __('Image is required'),
            'data.image.image' => __('The file must be a valid image.'),
            'data.image.mimes' => __('Image must be: JPG, PNG, GIF, or WebP.'),
            'data.image.max' => __('Image size must not exceed 3MB.'),
            'data.image.dimensions' => __('Image must be at least 200x200 pixels.'),
        ];
    }

    public function save()
    {
        $this->validate();

        $locale = $this->data['locale'] = app()->getLocale();
        $this->data['created_by'] = auth()->user()->id;

        $product = Product::create(($this->data));

        if (isset($this->data['tags'])) {
            $tags = collect($this->data['tags'])->map(function ($name) {
                $tag = Tag::whereTranslation('name', $name)->first();

                if (!$tag) {
                    $tag = new Tag();
                    $tag->translateOrNew(app()->getLocale())->name = $name;
                    $tag->save();
                }

                return $tag->id;
            });

            $product->tags()->sync($tags);
        }

        if (isset($this->data['labels'])) {
            $labels = collect($this->data['labels'])->map(function ($name) {
                $label = Label::whereTranslation('name', $name)->first();

                if (!$label) {
                    $label = new Label();
                    $label->translateOrNew(app()->getLocale())->name = $name;
                    $label->save();
                }

                return $label->id;
            });

            $product->labels()->sync($labels);
        }

        if (isset($this->data['category_id'])) {
            $product->categories()->sync($this->data['category_id']);
        }

        if (isset($this->data['branches'])) {
            $product->branches()->sync($this->data['branches']);
        }

        // حفظ Addons
        if (isset($this->data['addons']) && !empty($this->data['addons'])) {
            $product->addons()->detach();

            foreach ($this->data['addons'] as $addonData) {
                $pivotData = [
                    'addon_id'                => $addonData['addon_id'],
                    'product_id'              => $product->id,
                    'is_required'             => $addonData['is_required'] ?? false,
                    'min_selection'           => $addonData['min_selection'] ?? 1,
                    'max_selection'           => $addonData['max_selection'] ?? null,
                    'max_quantity_per_option' => $addonData['max_quantity_per_option'] ?? 1,
                    'created_by'              => auth()->id(),
                    'created_at'              => now(),
                    'updated_at'              => now(),
                ];

                $addonProductId = DB::table(config('items.table_prefix') . 'addon_product')->insertGetId($pivotData);

                if (!empty($addonData['allowed_options'])) {
                    $optionsToInsert = [];
                    foreach ($addonData['allowed_options'] as $optionData) {
                        if (empty($optionData['addon_option_id'])) continue;

                        $optionsToInsert[] = [
                            'addon_product_id' => $addonProductId,
                            'addon_option_id'  => $optionData['addon_option_id'],
                            'price'            => $optionData['price'] ?? null,
                            'is_default'       => $optionData['is_default'] ?? 0,
                            'created_at'       => now(),
                            'updated_at'       => now(),
                        ];
                    }

                    if (!empty($optionsToInsert)) {
                        DB::table(config('items.table_prefix') . 'addon_product_option')->insert($optionsToInsert);
                    }
                }
            }
        }

        // إضافة الصورة الرئيسية
        if (isset($this->data['image'])) {
            $product->addImage($this->data['image']);
        }

        // إضافة المعرض - الكود المُحدّث
        if (isset($this->data['gallery']) && !empty($this->data['gallery'])) {
            $product->addGalleryImage($this->data['gallery']);
        }

        // حفظ الـ variations
        if (isset($this->data['variations']) && !empty($this->data['variations'])) {
            foreach ($this->data['variations'] as $variationData) {
                $variationFields = collect($variationData)
                    ->except(['attribute_values', 'image', 'gallery', 'category_id'])
                    ->toArray();

                $variationFields['parent_id'] = $product->id;
                $variationFields['created_by'] = auth()->user()->id;
                $variationFields['locale'] = $locale;

                $variation = Product::create($variationFields);

                // ربط قيم الخصائص
                if (isset($variationData['attribute_values']) && !empty($variationData['attribute_values'])) {
                    $variation->attributeValues()->sync($variationData['attribute_values']);
                }

                // حفظ صورة الـ variation
                if (isset($variationData['image']) && !empty($variationData['image'])) {
                    $variation->addImage($variationData['image']);
                }

                // حفظ معرض الصور للـ variation - الكود المُحدّث
                if (isset($variationData['gallery']) && !empty($variationData['gallery'])) {
                    $variation->addGalleryImage($variationData['gallery']);
                }

                // ربط التصنيفات للـ variation
                if (isset($variationData['category_id']) && !empty($variationData['category_id'])) {
                    $variation->categories()->sync($variationData['category_id']);
                }
            }
        }

        event(new ProductUpdated());

        // عرض رسالة نجاح
        $this->js('window.location.href = "' . route('products.index') . '"');
    }

    public function render()
    {
        return view('items::livewire.pages.product.add')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Products List'), 'url' => route('products.index')],
                ['title' => __('Add Product'), 'url' => route('products.add')],
            ]
        ]);
    }
}
