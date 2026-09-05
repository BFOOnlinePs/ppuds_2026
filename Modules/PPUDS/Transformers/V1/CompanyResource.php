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
            'contact_person' => $this->contact_person,
            'contact_info' => $this->contact_info,
            'status' => $this->status,
            'logo_url' => $this->getImageAttribute(),

            'branches' => BranchResource::collection($this->whenLoaded('branches')),

            'departments' => $this->whenLoaded('branches', fn () => $this->departmentsWithSupervisors()),

            'supervisors' => $this->whenLoaded('branches', function () {
                $supervisors = $this->branches->flatMap(function ($branch) {
                    return $branch->supervisors;
                })->unique('id')->values();

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
            'contact_person',
            'contact_info',
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
            AllowedFilter::partial('contact_person'),
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
            'branches.departments.supervisors',
            'branches.workingHours',
            'branches.supervisors',
        ];
    }

    private function departmentsWithSupervisors()
    {
        return $this->branches
            ->filter(fn ($branch) => $branch->relationLoaded('departments'))
            ->flatMap(function ($branch) {
                return $branch->departments->map(function ($department) use ($branch) {
                    $supervisorId = $department->pivot->user_id ?? null;
                    $supervisor = $this->resolveDepartmentSupervisor($branch, $department, $supervisorId);

                    return [
                        'id' => $department->id,
                        'name' => $department->name,
                        'branch_id' => $branch->id,
                        'branch_name' => $branch->name,
                        'supervisor_id' => $supervisorId,
                        'supervisor' => $supervisor ? UserResource::make($supervisor) : null,
                    ];
                });
            })
            ->values();
    }

    private function resolveDepartmentSupervisor($branch, $department, ?int $supervisorId)
    {
        if (! $supervisorId) {
            return null;
        }

        if ($department->relationLoaded('supervisors')) {
            return $department->supervisors->firstWhere('id', $supervisorId);
        }

        if ($branch->relationLoaded('supervisors')) {
            return $branch->supervisors->firstWhere('id', $supervisorId);
        }

        return null;
    }
}
