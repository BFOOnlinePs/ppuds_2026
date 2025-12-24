<?php

namespace Modules\Items\Transformers\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class OfferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'name'                 => $this->name,
            'description'          => $this->description,
            'image'                => $this->image,
            'type'                 => $this->type,
            'value'                => $this->value,
            'start_date'           => $this->start_date,
            'end_date'             => $this->end_date,
            'is_active'            => $this->is_active,
            'branch_id'            => $this->branch_id,
            'code'                 => $this->code,
            'min_purchase_amount'  => $this->min_purchase_amount,
            'usage_limit'          => $this->usage_limit,
            'usage_limit_per_user' => $this->usage_limit_per_user,
            'offerable_type'       => $this->offerable_type,
            'offerable_id'         => $this->offerable_id,
            'created_at'           => $this->created_at,
            'updated_at'           => $this->updated_at,
        ];
    }

    public static function allowedFields(): array
    {
        return [
            'id',
            'name',
            'description',
            'type',
            'value',
            'start_date',
            'end_date',
            'is_active',
            'branch_id',
            'code',
            'min_purchase_amount',
            'usage_limit',
            'usage_limit_per_user',
            'offerable_type',
            'offerable_id',
            'created_at',
            'updated_at',
        ];
    }

    public static function allowedFilters(): array
    {
        return [
            AllowedFilter::exact('id'),
            AllowedFilter::exact('type'),
            AllowedFilter::exact('is_active'),
            AllowedFilter::exact('branch_id'),
            AllowedFilter::exact('offerable_type'),
            AllowedFilter::exact('offerable_id'),
            AllowedFilter::exact('start_date'),
            AllowedFilter::exact('end_date'),
            AllowedFilter::exact('created_at'),
            AllowedFilter::exact('updated_at'),

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

            AllowedFilter::callback('branch_id', function ($q, $v) {
                $q->where('branch_id', $v);
            })
        ];
    }

    public static function allowedSorts(): array
    {
        return [
            'id',
            'type',
            'value',
            'start_date',
            'end_date',
            'is_active',
            'branch_id',
            'offerable_type',
            'offerable_id',
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
