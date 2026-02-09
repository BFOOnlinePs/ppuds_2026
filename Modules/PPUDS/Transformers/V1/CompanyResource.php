<?php

namespace Modules\PPUDS\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Database\Eloquent\Builder;
use Modules\Branch\Transformers\V1\BranchResource;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class CompanyResource extends JsonResource
{

    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'name'                => $this->name,
            'description'         => $this->description,
            'company_category_id' => $this->company_category_id,
            'website'             => $this->website,
            'status'              => $this->status,
            'logo_url'            => $this->getFirstMediaUrl('logo'),

            'branches'            => BranchResource::collection($this->whenLoaded('branches')),

            'created_at'          => $this->created_at,
        ];
    }

    public static function allowedFields(): array
    {
        return [
            'id', 'name', 'description',
            'company_category_id', 'website', 'status', 'created_at'
        ];
    }

    public static function allowedFilters(): array
    {
        return [
            AllowedFilter::callback('name', fn (Builder $query, $value) => $query->whereTranslationLike('name', "%{$value}%")),
            AllowedFilter::exact('company_category_id'),
            AllowedFilter::exact('website'),
            AllowedFilter::exact('status'),
        ];
    }

    public static function allowedSorts(): array
    {
        return [
            AllowedSort::field('id'),
            AllowedSort::field('name'),
            AllowedSort::field('created_at'),
        ];
    }

    public static function allowedIncludes(): array
    {
        return [
            'createdBy',
            'branches',
            'branches.departments',
            'branches.workingHours'
        ];
    }
}
