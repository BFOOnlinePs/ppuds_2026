<?php

namespace Modules\Core\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Traits\Concerns\SelectsFieldsFromApi;
use Spatie\QueryBuilder\AllowedFilter;

class CurrencyResource extends JsonResource
{
    use SelectsFieldsFromApi;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name, // من الترجمة
            'plural_name' => $this->plural_name, // من الترجمة
            'code' => $this->code,
            'symbol' => $this->symbol,
            'symbol_position' => $this->symbol_position,
            'decimal_places' => $this->decimal_places,
            'exchange_rate' => $this->exchange_rate,
            'is_active' => (bool) $this->is_active,
            'is_default' => (bool) $this->is_default,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    public static function allowedFields(): array
    {
        return [
            'id', 'code', 'symbol', 'symbol_position',
            'decimal_places', 'exchange_rate', 'is_active',
            'is_default', 'created_at'
        ];
    }

    public static function allowedSorts(): array
    {
        return ['id', 'code', 'exchange_rate', 'is_active', 'is_default', 'created_at'];
    }

    public static function allowedFilters(): array
    {
        return [
            AllowedFilter::exact('id'),
            AllowedFilter::exact('code'),
            AllowedFilter::exact('is_active'),
            AllowedFilter::exact('is_default'),
            // البحث عن طريق الاسم في جدول الترجمات
            AllowedFilter::callback('name', function ($query, $value) {
                $query->whereTranslationLike('name', "%{$value}%");
            }),
        ];
    }

    public static function allowedIncludes(): array
    {
        return [];
    }
}
