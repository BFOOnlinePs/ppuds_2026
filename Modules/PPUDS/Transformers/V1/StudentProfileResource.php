<?php

namespace Modules\PPUDS\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class StudentProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_number' => $this->student_number,
            'user_id' => $this->user_id,
            'dob' => $this->dob,
            'gender' => $this->gender,
            'cv_status' => $this->cv_status,
            'tawjihi_gpa' => $this->tawjihi_gpa,
            'enrollment_year' => $this->enrollment_year,
            'semester_level' => $this->semester_level,
            'major_id' => $this->major_id,
            'linkedin_url' => $this->linkedin_url,
            'behance_url' => $this->behance_url,
            'github_url' => $this->github_url,
            'major' => $this->whenLoaded('major'),
            'cv' => $this->getFirstMediaUrl('cv'),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
        ];
    }

    public static function allowedFields(): array
    {
        return [
            'id', 'student_number', 'user_id', 'dob', 'gender', 'cv_status', 'tawjihi_gpa', 'enrollment_year', 'semester_level', 'major_id', 'linkedin_url', 'behance_url', 'github_url', 'created_by', 'created_at',
        ];
    }

    public static function allowedFilters(): array
    {
        return [
            AllowedFilter::exact('id'),
            AllowedFilter::exact('student_number'),
            AllowedFilter::exact('user_id'),
            AllowedFilter::exact('gender'),
            AllowedFilter::exact('cv_status'),
            AllowedFilter::exact('enrollment_year'),
            AllowedFilter::exact('semester_level'),
            AllowedFilter::exact('major_id'),
        ];
    }

    public static function allowedSorts(): array
    {
        return [
            AllowedSort::field('dob'),
            AllowedSort::field('created_at'),
            AllowedSort::field('id'),
        ];
    }

    public static function allowedIncludes(): array
    {
        return ['media', 'createdBy', 'major'];
    }
}
