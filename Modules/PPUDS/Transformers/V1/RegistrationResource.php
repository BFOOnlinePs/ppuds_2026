<?php

namespace Modules\PPUDS\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

/**
 * @OA\Schema(
 * schema="RegistrationResource",
 * title="Registration Resource",
 * description="Registration details",
 * @OA\Xml(name="RegistrationResource"),
 * @OA\Property(property="id", type="integer", example=1),
 * @OA\Property(property="student_id", type="integer", example=10),
 * @OA\Property(property="course_id", type="integer", example=2),
 * @OA\Property(property="grade", type="string", example="A"),
 * @OA\Property(property="semester", type="string", example="First"),
 * @OA\Property(property="year", type="string", example="2024"),
 * @OA\Property(property="supervisor_id", type="integer", example=5),
 * @OA\Property(property="university_score", type="number", format="float", example=85.5),
 * @OA\Property(property="company_score", type="number", format="float", example=90.0),
 * @OA\Property(property="image", type="string", format="url", example="https://example.com/media/file.png"),
 * @OA\Property(property="created_by", type="integer", example=1),
 * @OA\Property(property="created_at", type="string", format="date-time")
 * )
 */
class RegistrationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'student_id'       => $this->student_id,
            'course_id'        => $this->course_id,
            'grade'            => $this->grade,
            'semester'         => $this->semester,
            'year'             => $this->year,
            'supervisor_id'    => $this->supervisor_id,
            'university_score' => $this->university_score,
            'company_score'    => $this->company_score,
            'final_file'       => $this->getFirstMediaUrl('final_file'),
            'student'          => $this->whenLoaded('student'),
            'course'           => $this->whenLoaded('course'),
            'supervisor'       => $this->whenLoaded('supervisor'),
            'created_by'       => $this->whenLoaded('createdBy'),
        ];
    }

    public static function allowedFields(): array
    {
        return [
            'id',
            'student_id',
            'course_id',
            'grade',
            'semester',
            'year',
            'supervisor_id',
            'university_score',
            'company_score',
            'created_by',
        ];
    }

    public static function allowedFilters(): array
    {
        return [
            AllowedFilter::exact('id'),
            AllowedFilter::exact('student_id'),
            AllowedFilter::exact('course_id'),
            AllowedFilter::exact('supervisor_id'),
            AllowedFilter::exact('semester'),
            AllowedFilter::exact('year'),
            AllowedFilter::exact('created_by'),
            AllowedFilter::partial('grade'),
        ];
    }

    public static function allowedSorts(): array
    {
        return [
            AllowedSort::field('id'),
            AllowedSort::field('student_id'),
            AllowedSort::field('course_id'),
            AllowedSort::field('semester'),
            AllowedSort::field('year'),
            AllowedSort::field('university_score'),
            AllowedSort::field('company_score'),
            AllowedSort::field('created_at'),
        ];
    }

    public static function allowedIncludes(): array
    {
        return [
            'student',
            'course',
            'supervisor',
            'createdBy',
        ];
    }
}
