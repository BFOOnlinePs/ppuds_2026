<?php

namespace Modules\Content\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Traits\Concerns\SelectsFieldsFromApi;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class BannerResource extends JsonResource
{
    use SelectsFieldsFromApi;
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'branch_id' => $this->branch_id,
            'image'     => $this->image,
            'bannable_type' => $this->bannable_type,
            'bannable_id' => $this->bannable_id,
            'bannable_model' => $this->bannable_model,
//            'bannable' => $this->bannable,
            'media' => $this->media,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    public static function allowedFields(): array
    {
        return [
            'id',
            'branch_id',
            'bannable_type',
            'bannable_id',
            'active',
            'created_at',
            'updated_at',
            'name',
            'description',
        ];
    }

    public static function allowedSorts(): array
    {
        return [
            AllowedSort::field('id'),
            AllowedSort::field('branch_id'),
            AllowedSort::field('created_at'),
            AllowedSort::field('updated_at'),
        ];
    }

    public static function allowedFilters(): array
    {
        return [
            AllowedFilter::exact('id'),
            AllowedFilter::partial('name', 'translations.name'),
            AllowedFilter::exact('branch_id'),
        ];
    }
}
