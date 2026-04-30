<?php

namespace Modules\PPUDS\Transformers\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

/**
 * @OA\Schema(
 * schema="FieldVisitResource",
 * title="Field Visit Resource",
 * description="Field Visit details",
 * @OA\Xml(name="FieldVisitResource"),
 * @OA\Property(property="id", type="integer", example=1),
 * @OA\Property(property="student_company_id", type="integer", example=5),
 * @OA\Property(property="supervisor_id", type="integer", example=3),
 * @OA\Property(property="visiting_place", type="string", example="Main Office"),
 * @OA\Property(property="visit_date", type="string", format="date", example="2024-05-20"),
 * @OA\Property(property="visit_time", type="string", format="time", example="09:00:00"),
 * @OA\Property(property="visit_duration", type="integer", example=60),
 * @OA\Property(property="notes", type="string", example="Everything went well"),
 * @OA\Property(property="created_by", type="integer", example=123),
 * @OA\Property(property="created_at", type="string", format="date-time")
 * )
 */

class FieldVisitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'student_company_id' => $this->student_company_id,
            'supervisor_id'      => $this->supervisor_id,
            'visiting_place'     => $this->visiting_place,
            'visit_date'         => $this->visit_date,
            'visit_time'         => $this->visit_time,
            'visit_duration'     => $this->visit_duration,
            'notes'              => $this->notes,
            'student_company'    => $this->whenLoaded('studentCompany'),
            'supervisor'         => $this->whenLoaded('supervisor'),
            'created_by'         => $this->whenLoaded('createdBy'),
        ];
    }

    public static function allowedFields(): array
    {
        return [
            'id',
            'student_company_id',
            'supervisor_id',
            'visiting_place',
            'visit_date',
            'visit_time',
            'visit_duration',
            'notes',
            'created_by',
        ];
    }

    public static function allowedFilters(): array
    {
        return [
            AllowedFilter::exact('id'),
            AllowedFilter::exact('student_company_id'),
            AllowedFilter::exact('supervisor_id'),
            AllowedFilter::partial('visiting_place'),
            AllowedFilter::exact('visit_date'),
            AllowedFilter::exact('created_by'),

            AllowedFilter::callback('university_supervisor', function (Builder $query, $value) {
                $query->whereHas('studentCompany.registration', function ($query) use ($value) {
                    $query->where('supervisor_id', $value);
                });
            }),
        ];
    }

    public static function allowedSorts(): array
    {
        return [
            AllowedSort::field('id'),
            AllowedSort::field('student_company_id'),
            AllowedSort::field('visit_date'),
            AllowedSort::field('visit_time'),
            AllowedSort::field('created_at'),
        ];
    }

    public static function allowedIncludes(): array
    {
        return [
            'createdBy',
            'studentCompany',
            'studentCompany.student',
            'supervisor'
        ];
    }
}
