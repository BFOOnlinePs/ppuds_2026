<?php

namespace Modules\Items\Livewire\Pages\Offer;

use App\View\Components\AppLayout;
use Dom\Text;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Str;
use Livewire\Component;
use Modules\Branch\Entities\Branch;
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\Items\Entities\Brand;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Offer;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\CategoryStatus;
use Modules\Items\Enums\OfferType;

class Add extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    public function mount()
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->model(Offer::class)
            ->schema([
                Grid::make(3)
                    ->schema([
                        Section::make()
                            ->columnSpan(2)
                            ->schema([
                                Grid::make(1)
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('name')
                                                    ->required()
                                                    ->label(__('Name')),

                                                Select::make('branch_id')
                                                    ->multiple()
                                                    ->options(Branch::get()->pluck('name', 'id'))
                                                    ->required()
                                                    ->live()
                                                    ->afterStateUpdated(function(Set $set) {
                                                        $set('offerable_id', null);                                                    })
                                                    ->label(__('Branch')),

                                                Select::make('offerable_model')
                                                    ->label(__('Offerable Model'))
                                                    ->searchable()
                                                    ->options([
                                                        'items_category' => __('Category'),
                                                        'items_product' => __('Product'),
                                                    ])
                                                    ->required()
                                                    ->live()
                                                    ->afterStateUpdated(fn($state, Set $set) => $set('bannable_id', null)),
                                                Select::make('offerable_id')
                                                    ->label(__('Offerable ID'))
                                                    ->searchable()
                                                    ->required()
                                                    ->options(function (Get $get) { // <-- نستخدم Get $get هنا
                                                        $modelType = $get('offerable_model');
                                                        $branchIds = $get('branch_id'); // <-- نحصل على الفروع المختارة

                                                        if ($modelType === 'items_category') {
                                                            // (بافتراض أن الأقسام غير مرتبطة بفرع)
                                                            return Category::get()->pluck('name', 'id');
                                                        }

                                                        if ($modelType === 'items_product') {

                                                            // إذا لم يتم اختيار أي فرع، أرجع مصفوفة فارغة
                                                            if (empty($branchIds)) {
                                                                return [];
                                                            }

                                                            // نبدأ ببناء الكويري
                                                            // نفترض أنك تريد فقط المنتجات الأساسية (ليست variations)
                                                            $productQuery = Product::where('parent_id', null);

                                                            // نستخدم whereHas لفلترة المنتجات
                                                            // التي تنتمي إلى *أي* من الفروع المختارة
                                                            $productQuery->whereHas('branches', function ($query) use ($branchIds) {
                                                                // 'branch_id' هو اسم العمود في الجدول الوسيط
                                                                // بناءً على ملف Product.php
                                                                $query->whereIn(config('items.table_prefix') . 'branch_product.branch_id', $branchIds);
                                                            });

                                                            // أرجع المنتجات المطابقة
                                                            return $productQuery->get()->pluck('name', 'id');
                                                        }

                                                        return [];
                                                    })
                                                    ->hidden(fn(Get $get) => !$get('offerable_model')),

                                                Select::make('type')
                                                    ->options(OfferType::class)
                                                    ->required(),

                                                TextInput::make('value')
                                                    ->numeric()
                                                    ->required()
                                                    ->label(__('Value')),

                                                TextInput::make('code')
                                                    ->columnSpanFull()
                                                    ->label(__('Code'))
                                                    ->unique(Offer::class, 'code'),

                                                TextInput::make('min_purchase_amount')
                                                    ->columnSpanFull()
                                                    ->required()
                                                    ->numeric()
                                                    ->label(__('Min Purchase Amount')),

                                                TextInput::make('usage_limit')
                                                    ->columnSpan(1)
                                                    ->required()
                                                    ->numeric()
                                                    ->label(__('Usage Limit')),

                                                TextInput::make('usage_limit_per_user')
                                                    ->columnSpan(1)
                                                    ->required()
                                                    ->numeric()
                                                    ->label(__('Usage Limit Per User')),

                                                DateTimePicker::make('start_date')
                                                    ->columnSpan(1)
                                                    ->label(__('Start Date')),

                                                DateTimePicker::make('end_date')
                                                    ->columnSpan(1)
                                                    ->label(__('End Date')),

                                                Textarea::make('description')
                                                    ->label(__('Description'))
                                                    ->rows(3)
                                                    ->columnSpan(2),
                                            ]),
                                    ]),
                            ]),

                        Section::make()
                            ->columnSpan(1)
                            ->schema([
                                SpatieMediaLibraryFileUpload::make('image')
                                    ->required()
                                    ->image()
                                    ->disk('offers')
                                    ->collection('offer')
                                    ->label(__('Image'))
                                    ->imageResizeMode('cover')
                                    ->imageCropAspectRatio('1:1')
                                    ->imageResizeTargetWidth('400')
                                    ->imageResizeTargetHeight('400')
                                    ->maxSize(10000),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function rules(): array
    {
        return [
            'data.name' => ['required', 'string', 'max:255'],
            'data.image' => [
                'required',
                'image',
                'mimes:jpeg,jpg,png,gif,webp',
                'max:2048',
            ],
            'data.branch_id' => ['required', 'exists:branches,id'],
            'data.value' => ['required', 'numeric', 'min:0'],
            'data.code' => ['nullable', 'string', 'max:50', 'unique:offers,code'],
            'data.min_purchase_amount' => ['nullable', 'numeric', 'min:0'],
            'data.usage_limit' => ['nullable', 'integer', 'min:1'],
            'data.usage_limit_per_user' => ['nullable', 'integer', 'min:1'],
            'data.start_date' => ['nullable', 'date'],
            'data.end_date' => ['nullable', 'date', 'after_or_equal:data.start_date'],
        ];
    }

    public function messages(): array
    {
        return [
            'data.name.required' => __('Name is required'),
            'data.image.required' => __('Image is required'),
            'data.image.image' => __('The file must be a valid image.'),
            'data.image.mimes' => __('Image must be: JPG, PNG, GIF, or WebP.'),
            'data.image.max' => __('Image size must not exceed 3MB.'),
            'data.image.dimensions' => __('Image must be at least 200x200 pixels.'),
            'data.branch_id.required' => __('Branch is required'),
            'data.branch_id.exists' => __('Selected branch does not exist'),
            'data.value.required' => __('Value is required'),
            'data.value.numeric' => __('Value must be a number'),
            'data.value.min' => __('Value must be at least 0'),
            'data.code.unique' => __('Code must be unique'),
            'data.code.max' => __('Code must not exceed 50 characters'),
            'data.min_purchase_amount.numeric' => __('Min Purchase Amount must be a number'),
            'data.min_purchase_amount.min' => __('Min Purchase Amount must be at least 0'),
            'data.usage_limit.integer' => __('Usage Limit must be an integer'),
            'data.usage_limit.min' => __('Usage Limit must be at least 1'),
            'data.usage_limit_per_user.integer' => __('Usage Limit Per User must be an integer'),
            'data.usage_limit_per_user.min' => __('Usage Limit Per User must be at least 1'),
            'data.start_date.date' => __('Start Date must be a valid date'),
            'data.end_date.date' => __('End Date must be a valid date'),
            'data.end_date.after_or_equal' => __('End Date must be a date after or equal to Start Date')
        ];
    }


    public function save()
    {
        $this->validate();

        $locale = $this->data['locale'] = app()->getLocale();
        $this->data['created_by'] = auth()->user()->id;

        $modelType = $this->data['offerable_model'];
        $modelClassMap = [
            'items_category' => Category::class,
            'items_product' => Product::class,
        ];
        $offerableTypeClass = $modelClassMap[$modelType] ?? null;
        if (!$offerableTypeClass) {
            return;
        }
        $this->data['offerable_type'] = $offerableTypeClass;
        unset($this->data['offerable_model']);

        $imageFile = $this->data['image'];
        $first = true;
        $firstOffer = null;

        foreach ($this->data['branch_id'] as $key) {
            $data = $this->data;
            $data['branch_id'] = $key;

            $offer = Offer::create($data);

            if ($first) {
                if (isset($imageFile)) {
                    $offer->addImage($imageFile);
                }
                $firstOffer = $offer;
                $first = false;
            } else {
                if ($firstOffer && $firstOffer->getFirstMedia('offer')) {
                    $mediaPath = $firstOffer->getFirstMedia('offer')->getPath();
                    $offer->addMedia($mediaPath)->preservingOriginal()->toMediaCollection('offer');
                }
            }
        }

//        $offers = Offer::create($this->data);
//
//        if (isset($this->data['image'])) {
//            $offers->addImage($this->data['image']);
//        }
        $this->redirectRoute('offers.index');
    }

    public function render()
    {
        return view('items::livewire.pages.category.add')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Offers List'), 'url' => route('offers.index')],
                ['title' => __('Add Offer'), 'url' => route('offers.add')],
            ]
        ]);
    }
}
