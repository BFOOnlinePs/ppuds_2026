<?php

namespace Modules\Items\Livewire\Pages\Product;

use App\View\Components\AppLayout;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Branch\Entities\Branch;
use Modules\Items\Entities\BranchPrice;
use Modules\Items\Entities\Product;
use Illuminate\Database\Eloquent\Collection;

class BranchPricings extends Component
{
    public Product $product;

    // قائمة الفروع المتاحة
    public Collection $branches;

    // مصفوفة تربط branch_id => price وتُستخدم للـ wire:model في الفورم
    public array $prices = [];

    public function mount($product)
    {
        if ($product instanceof Product) {
            $this->product = $product;
        } else {
            $this->product = Product::findOrFail($product);
        }

        // جلب جميع الفروع
        $this->branches = Branch::all();

        // تهيئة مصفوفة الأسعار لكل فرع للمنتج الرئيسي
        $this->prices[$this->product->id] = [];
        foreach ($this->branches as $branch) {
            $existing = BranchPrice::where('product_id', $this->product->id)
                ->where('branch_id', $branch->id)
                ->first();
            $this->prices[$this->product->id][$branch->id] = $existing?->price ?? '';
        }

        // تهيئة مصفوفة الأسعار لكل فرع للمتغيرات
        foreach ($this->product->variations as $variation) {
            $this->prices[$variation->id] = [];
            foreach ($this->branches as $branch) {
                $existing = BranchPrice::where('product_id', $variation->id)
                    ->where('branch_id', $branch->id)
                    ->first();
                $this->prices[$variation->id][$branch->id] = $existing?->price ?? '';
            }
        }
    }

    protected function rules(): array
    {
        // تحقق من أن كل سعر إن وُضع هو رقم وغير سالب
        return [
            'prices.*.*' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * حفظ أسعار المنتج لكل فرع
     */
    public function save()
    {
        $this->validate();

        foreach ($this->prices as $productOrVariationId => $branchPrices) {
            foreach ($branchPrices as $branchId => $value) {
                if ($value === null || $value === '') {
                    BranchPrice::where('product_id', $productOrVariationId)
                        ->where('branch_id', $branchId)
                        ->delete();
                    continue;
                }

                BranchPrice::updateOrCreate(
                    [
                        'product_id' => $productOrVariationId,
                        'branch_id' => $branchId,
                    ],
                    [
                        'price' => $value,
                    ]
                );
            }
        }

        // إعادة تحميل علاقات المنتج
        $this->product->loadMissing(['branchProducts']);

        Toaster::success(__('Prices saved successfully'));
    }

    public function render()
    {
        return view('items::livewire.pages.product.branch-pricings')
            ->layout(AppLayout::class, [
                'breadcrumbs' => [
                    ['title' => __('Home'), 'url' => route('home')],
                    ['title' => __('Products List'), 'url' => route('products.index')],
                ]
            ]);
    }
}
