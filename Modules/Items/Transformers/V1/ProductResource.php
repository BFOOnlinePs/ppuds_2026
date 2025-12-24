<?php

namespace Modules\Items\Transformers\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Items\Entities\ProductTranslation;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\AllowedSort;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isVariable = $this->resource->relationLoaded('variations') && $this->variations->isNotEmpty();
        $directOffers = $this->whenLoaded('activeOffers') ? $this->resource->activeOffers->collect() : collect();

        $categoryOffers = collect();
        if ($this->resource->relationLoaded('categories')) {
            foreach ($this->resource->categories as $category) {
                if ($category->relationLoaded('offers')) {
                    $categoryOffers = $categoryOffers->merge($category->offers);
                }
            }
        }

        $allOffers = $directOffers->merge($categoryOffers)->unique('id');
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'short_description' => $this->short_description,
            'slug' => $this->slug,
            'barcode' => $this->barcode,
            'sku' => $this->sku,
            'discount' => $this->discount,
            'base_price' => $this->base_price,
            'sale_price' => $this->sale_price,
            'price' => $this->price,
            'cost_price' => $this->cost_price,
            'wholesale_price' => $this->wholesale_price,
            'stock_quantity' => $this->stock_quantity,
            'manage_stock' => $this->manage_stock,
            'low_stock_threshold' => $this->low_stock_threshold,
            'stock_status' => $this->stock_status,
            'allow_backorder' => $this->allow_backorder,
            'max_quantity_per_order' => $this->max_quantity_per_order,
            'discount_type' => $this->discount_type,
            'discount_value' => $this->discount_value,
            'discount_start_date' => $this->discount_start_date,
            'discount_end_date' => $this->discount_end_date,
            'min_quantity_discount' => $this->min_quantity_discount,
            'weight' => $this->weight,
            'status' => $this->status,
            'is_featured' => $this->is_featured,
            'is_digital' => $this->is_digital,
            'meta_data' => $this->meta_data,
            'parent_id' => $this->parent_id,
            'brand_id' => $this->brand_id,
            'is_active' => $this->is_active,
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'image' => $this->image,
            'gallery'   => $this->getGalleryAttribute(),
            'is_variable' => $isVariable,
            'variations' => ProductVariationResource::collection($this->whenLoaded('variations')),
            'addons' => AddonResource::collection($this->whenLoaded('addons')),
            'offers' => OfferResource::collection($allOffers),
            'translations' => $this->whenLoaded('translations')
        ];
    }

    public static function allowedFields(): array
    {
        // ... (هذه الدالة صحيحة كما هي، لا تغيير) ...
        return [
            'id', 'slug', 'barcode', 'sku', 'discount', 'base_price', 'sale_price',
            'cost_price', 'wholesale_price', 'stock_quantity', 'manage_stock',
            'low_stock_threshold', 'stock_status', 'allow_backorder',
            'max_quantity_per_order', 'discount_type', 'discount_value',
            'discount_start_date', 'discount_end_date', 'min_quantity_discount',
            'weight', 'status', 'is_featured', 'is_digital', 'meta_data',
            'parent_id', 'brand_id', 'is_active', 'created_at', 'updated_at',
        ];
    }

    /**
     * ✨ الخطوة 1: الدالة المساعدة (مفتاح الحل)
     * هذه الدالة تقرأ الفلتر وتحوله إلى أرقام (Integers)
     */
    private static function getBranchIdsFromFilter(): array
    {
        $branchFilter = request()->input('filter.branches');
        if (!$branchFilter) {
            return [];
        }
        $branchIds = is_array($branchFilter) ? $branchFilter : explode(',', $branchFilter);

        // ✨ هذا هو السطر الأهم: يحول ["2"] (نص) إلى [2] (رقم)
        return array_map('intval', array_filter($branchIds));
    }


    public static function allowedFilters(): array
    {
        return [
            AllowedFilter::callback('name', function ($query, $value){
                $query->whereTranslationLike('name', "%{$value}%");
            }),
            // ... (كل الفلاتر الأخرى) ...
            AllowedFilter::exact('id'),
            AllowedFilter::exact('slug'),
            AllowedFilter::exact('status'),
            AllowedFilter::callback('parent_id', function ($query, $value) {
                // نتحقق إذا كانت القيمة المرسلة هي الكلمة "null"
                if ($value === 'null' || $value === null) {
                    // إذا كانت "null"، نستخدم whereNull
                    $query->whereNull('parent_id');
                } else {
                    // وإلا، نستخدم الفلتر العادي
                    $query->where('parent_id', $value);
                }
            }),

            AllowedFilter::exact('brand_id'),

            AllowedFilter::callback('category_id', function ($q, $v) {
                $categoryIds = is_array($v) ? $v : explode(',', $v);
                $categoryIds = array_filter($categoryIds);
                if (empty($categoryIds)) {
                    return;
                }
                $q->whereHas('categories', function ($c) use ($categoryIds) {
                    $c->whereIn('items_categories.id', $categoryIds);
                });
            }),

            // ✨ الخطوة 2: إصلاح فلتر المنتجات
            // نستخدم الدالة المساعدة هنا
            AllowedFilter::callback('branches', function ($q, $v) {

                $branchIds = self::getBranchIdsFromFilter(); // استخدام الدالة المساعدة

                if (empty($branchIds)) {
                    return;
                }

                $q->whereHas('branches', function ($b) use ($branchIds) {
                    // نستخدم اسم الجدول الصحيح 'branch_branches.id' لتجنب أخطاء SQL
                    $b->whereIn('branch_branches.id', $branchIds);
                });
            })
        ];
    }

    public static function allowedSorts(): array
    {
        // ... (هذه الدالة صحيحة كما هي، لا تغيير) ...
        return [
            'id', 'slug', 'status', 'base_price', 'sale_price', 'stock_quantity',
            'created_at', 'updated_at',
            AllowedSort::callback('name', function ($query, $directionOrDescending) {
                if (is_bool($directionOrDescending)) {
                    $dir = $directionOrDescending ? 'asc' : 'desc';
                } else {
                    $d = strtolower((string)$directionOrDescending);
                    $dir = in_array($d, ['asc', 'desc'], true) ? $d : 'asc';
                }
                $query->orderByTranslation('name', $dir);
            }),
        ];
    }

    public static function allowedIncludes(): array
    {
        $branchIds = self::getBranchIdsFromFilter();
        return [
            'variations.attributeValues.attribute',
            'addons',
            AllowedInclude::callback('branchProducts', function ($query) use ($branchIds) {
                if (!empty($branchIds)) {
                    $query->whereIn('branch_id', $branchIds);
                }
            }),
            AllowedInclude::callback('activeOffers', function ($query) use ($branchIds) {
                if (!empty($branchIds)) {
                    $query->whereIn('branch_id', $branchIds);
                }
            }),
            AllowedInclude::callback('categories.offers', function ($query) use ($branchIds) {
                if (!empty($branchIds)) {
                    $query->where('is_active', true) // (اختياري: التأكد أن عرض القسم فعال)
                    ->whereIn('branch_id', $branchIds);
                }
            }),
        ];
    }
}
