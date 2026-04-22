<?php

namespace Modules\PPUDS\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Transformers\V1\UserResource;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

/**
 * @OA\Schema(
 * schema="WorkExperienceResource",
 * title="Work Experience Resource",
 * description="Alumni work experience details",
 * @OA\Xml(name="WorkExperienceResource"),
 * @OA\Property(property="id", type="integer", example=1),
 * @OA\Property(property="user_id", type="integer", example=10),
 * @OA\Property(property="company_id", type="integer", nullable=true, example=5),
 * @OA\Property(property="company_name", type="string", nullable=true, example="Google"),
 * @OA\Property(property="resolved_company_name", type="string", example="Google"),
 * @OA\Property(property="position", type="string", example="Senior Backend Developer"),
 * @OA\Property(property="sector", type="string", example="Information Technology"),
 * @OA\Property(property="location", type="string", nullable=true, example="Ramallah"),
 * @OA\Property(property="start_date", type="string", format="date", example="2022-01-15"),
 * @OA\Property(property="end_date", type="string", format="date", nullable=true, example="2024-03-01"),
 * @OA\Property(property="is_current", type="boolean", example=false),
 * @OA\Property(property="description", type="string", nullable=true, example="Developed RESTful APIs using Laravel..."),
 * @OA\Property(property="created_at", type="string", format="date-time")
 * )
 */
class WorkExperienceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'user_id'               => $this->user_id,
            'company_name'          => $this->company_name,

            'position'              => $this->position,
            'sector'                => CompanyCategoryResource::make($this->whenLoaded('sectorCategory')),
            'location'              => $this->location,
            'start_date'            => $this->start_date,
            'end_date'              => $this->end_date,
            'is_current'            => $this->is_current,
            'description'           => $this->description,
            'created_at'            => $this->created_at,

            'user'                  => UserResource::make($this->whenLoaded('user')),
        ];
    }

    public static function allowedFields(): array
    {
        return [
            'id',
            'user_id',
            'company_name',
            'position',
            'sector',
            'location',
            'start_date',
            'end_date',
            'is_current',
            'description',
            'created_at',
        ];
    }

    public static function allowedFilters(): array
    {
        return [
            AllowedFilter::exact('id'),
            AllowedFilter::exact('user_id'),
            AllowedFilter::exact('is_current'),
            AllowedFilter::exact('sector'),

            AllowedFilter::callback('company_name', fn(Builder $query, $value) => $query->where('company_name', 'LIKE', "%{$value}%")),
            AllowedFilter::callback('position', fn(Builder $query, $value) => $query->where('position', 'LIKE', "%{$value}%")),
            AllowedFilter::callback('location', fn(Builder $query, $value) => $query->where('location', 'LIKE', "%{$value}%")),
        ];
    }

    public static function allowedSorts(): array
    {
        return [
            AllowedSort::field('id'),
            AllowedSort::field('start_date'),
            AllowedSort::field('end_date'),
            AllowedSort::field('created_at'),
            AllowedSort::field('is_current'),
        ];
    }

    public static function allowedIncludes(): array
    {
        return [
            'user',
            'sectorCategory',
            'createdBy',
        ];
    }
}
