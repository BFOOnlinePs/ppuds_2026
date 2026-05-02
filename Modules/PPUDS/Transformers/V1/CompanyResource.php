<?php

namespace Modules\PPUDS\Transformers\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Branch\Transformers\V1\BranchResource;
use Modules\Core\Transformers\V1\UserResource;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'company_category_id' => $this->company_category_id,
            'website' => $this->website,
            'status' => $this->status,
            'logo_url' => $this->getFirstMediaUrl('logo'),

            'branches' => BranchResource::collection($this->whenLoaded('branches')),

            'supervisors' => $this->whenLoaded('branches', function () {
                $supervisors = $this->branches->flatMap(function ($branch) {
                    return $branch->supervisors;
                })->unique('id')->values();

                $departments = $this->branches->flatMap(function ($branch) {
                    return $branch->departments;
                })->unique('id')->values();

                $supervisors = $supervisors->merge($departments);

                return UserResource::collection($supervisors);
            }),

            'created_at' => $this->created_at,
        ];
    }

    public static function allowedFields(): array
    {
        return [
            'id',
            'name',
            'description',
            'company_category_id',
            'website',
            'status',
            'created_at',
        ];
    }

    public static function allowedFilters(): array
    {
        return [
            AllowedFilter::callback('name', fn (Builder $query, $value) => $query->whereTranslationLike('name', "%{$value}%")),
            AllowedFilter::exact('company_category_id'),
            AllowedFilter::exact('website'),
            AllowedFilter::exact('status'),

            AllowedFilter::callback('city_id', function (Builder $query, $value) {
                $query->whereHas('branches', function (Builder $branchQuery) use ($value) {
                    if (is_array($value)) {
                        $branchQuery->whereIn('city_id', $value);
                    } else {
                        $branchQuery->where('city_id', $value);
                    }
                });
            }),

            AllowedFilter::callback('country_id', function (Builder $query, $value) {
                $query->whereHas('branches', function (Builder $branchQuery) use ($value) {
                    if (is_array($value)) {
                        $branchQuery->whereIn('country_id', $value);
                    } else {
                        $branchQuery->where('country_id', $value);
                    }
                });
            }),

            AllowedFilter::callback('has_current_students', function (Builder $query, $value) {
                if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
                    $query->hasCurrentStudents();
                }
            }),
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
            'branches.workingHours',
            'branches.supervisors',
        ];
    }
}
