<?php

namespace Modules\Items\Transformers\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Items\Entities\ProductTranslation;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class AttributeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'values' => AttributeValueResource::collection($this->whenLoaded('attributeValues')),
        ];
    }

    public static function allowedFields(): array
    {
        return [
            'id',
            'slug',
            'barcode',
            'sku',
            'discount',
            'base_price',
            'sale_price',
            'cost_price',
            'wholesale_price',
            'stock_quantity',
            'manage_stock',
            'low_stock_threshold',
            'stock_status',
            'allow_backorder',
            'max_quantity_per_order',
            'discount_type',
            'discount_value',
            'discount_start_date',
            'discount_end_date',
            'min_quantity_discount',
            'weight',
            'status',
            'is_featured',
            'is_digital',
            'meta_data',
            'parent_id',
            'brand_id',
            'is_active',
            'created_at',
            'updated_at',
        ];
    }

    public static function allowedFilters(): array
    {
        return [
            AllowedFilter::exact('id'),
            AllowedFilter::exact('slug'),
            AllowedFilter::exact('status'),
            AllowedFilter::exact('parent_id'),
            AllowedFilter::exact('brand_id'),
            AllowedFilter::exact('is_active'),
            AllowedFilter::exact('is_featured'),
            AllowedFilter::exact('is_digital'),
            AllowedFilter::callback('discount', function (Builder $query, $value) {
                if(!is_array($value)) {
                    $query->where('discount', $value);
                    return;
                }

                // Handle greater than (>)
                if (isset($value['gt'])){
                    $query->where('discount', '>', $value['gt']);
                }

                // Handle less than (<)
                if (isset($value['lt'])){
                    $query->where('discount', '<', $value['lt']);
                }

                // Handle greater than or equal to (>=)
                if (isset($value['gte'])){
                    $query->where('discount', '>=', $value['gte']);
                }

                // Handle less than or equal to (<=)
                if (isset($value['lte'])){
                    $query->where('discount', '<=', $value['lte']);
                }

                // Handle between (min and max)
                if (isset($value['min']) || isset($value['max'])) {
                    $query->whereBetween('discount', [$value['min'], $value['max']]);
                }

                if (isset($value['equal'])){
                    $query->where('discount', $value['equal']);
                }
            }),
            AllowedFilter::exact('base_price'),
            AllowedFilter::exact('sale_price'),
            AllowedFilter::exact('cost_price'),
            AllowedFilter::exact('wholesale_price'),
            AllowedFilter::exact('stock_quantity'),
            AllowedFilter::exact('manage_stock'),
            AllowedFilter::exact('low_stock_threshold'),
            AllowedFilter::exact('stock_status'),
            AllowedFilter::exact('allow_backorder'),
            AllowedFilter::exact('max_quantity_per_order'),
            AllowedFilter::exact('discount_type'),
            AllowedFilter::exact('discount_value'),
            AllowedFilter::exact('discount_start_date'),
            AllowedFilter::exact('discount_end_date'),
            AllowedFilter::exact('min_quantity_discount'),
            AllowedFilter::exact('weight'),
            AllowedFilter::exact('is_featured'),
            AllowedFilter::exact('is_digital'),

            AllowedFilter::callback('name', function ($q, $v) {
                $q->whereHas('translations', function ($t) use ($v) {
                    $t->where('locale', app()->getLocale())
                        ->where('name', 'like', '%'.$v.'%');
                });
            }),

            AllowedFilter::callback('description', function ($q, $v) {
                $q->whereHas('translations', function ($t) use ($v) {
                    $t->where('locale', app()->getLocale())
                        ->where('description', 'like', '%'.$v.'%');
                });
            }),

            AllowedFilter::callback('short_description', function ($q, $v) {
                $q->whereHas('translations', function ($t) use ($v) {
                    $t->where('locale', app()->getLocale())
                        ->where('short_description', 'like', '%'.$v.'%');
                });
            }),

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
        ];
    }

    public static function allowedSorts(): array
    {
        return [
            'id',
            'slug',
            'status',
            'base_price',
            'sale_price',
            'stock_quantity',
            'created_at',
            'updated_at',
            AllowedSort::callback('name', function ($query, $directionOrDescending) {
                if (is_bool($directionOrDescending)) {
                    $dir = $directionOrDescending ? 'asc' : 'desc';
                } else {
                    $d = strtolower((string)$directionOrDescending);
                    $dir = in_array($d, ['asc','desc'], true) ? $d : 'asc';
                }
                $query->orderByTranslation('name', $dir);
            }),
        ];
    }
}
