<?php

namespace Modules\PPUDS\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class StudentAttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'student_company_id' => $this->student_company_id,
            'attendance_date'    => $this->attendance_date?->format('Y-m-d'),

            'check_in' => [
                'time'      => $this->check_in?->format('H:i:s'),
                'latitude'  => $this->check_in_latitude,
                'longitude' => $this->check_in_longitude,
            ],

            'check_out' => [
                'time'      => $this->check_out?->format('H:i:s'),
                'latitude'  => $this->check_out_latitude,
                'longitude' => $this->check_out_longitude,
            ],

            'status'      => $this->status,
            'description' => $this->description,
            'created_by'  => $this->created_by,
            'created_at'  => $this->created_at?->toIso8601String(),

            'student_company' => new StudentCompanyResource($this->whenLoaded('studentCompany')),
            'creator'         => $this->whenLoaded('createdBy'),
        ];
    }

    public static function allowedFields(): array
    {
        return [
            'id',
            'student_company_id',
            'attendance_date',
            'status',
            'description',
        ];
    }

    public static function allowedFilters(): array
    {
        return [
            AllowedFilter::exact('id'),
            AllowedFilter::exact('student_company_id'),
            AllowedFilter::exact('status'),
            AllowedFilter::scope('date_between'),
            AllowedFilter::exact('attendance_date'),
        ];
    }

    public static function allowedSorts(): array
    {
        return [
            AllowedSort::field('attendance_date'),
            AllowedSort::field('created_at'),
            AllowedSort::field('id'),
        ];
    }

    public static function allowedIncludes(): array
    {
        return ['studentCompany', 'studentCompany.student', 'createdBy'];
    }
}
