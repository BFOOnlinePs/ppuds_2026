<?php

namespace Modules\PPUDS\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class StudentAttendanceReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                        => $this->id,
            'student_attendance_id'     => $this->student_attendance_id,
            'report_text'               => $this->report_text,
            'company_feedback'          => $this->company_feedback,
            'academic_feedback'         => $this->academic_feedback,
            'submit_latitude'           => $this->submit_latitude,
            'submit_longitude'          => $this->submit_longitude,
            'file_report'               => $this->getMultipleImage(),
            'created_by'                => $this->whenLoaded('createdBy'),
        ];
    }

    public static function allowedFields(): array
    {
        return [
            'id',
            'student_attendance_id',
            'report_text',
            'company_feedback',
            'academic_feedback',
            'submit_latitude',
            'submit_longitude',
            'created_by',
        ];
    }

    public static function allowedFilters(): array
    {
        return [
            AllowedFilter::exact('id'),
            AllowedFilter::exact('student_attendance_id'),
            AllowedFilter::partial('report_text'),
            AllowedFilter::partial('company_feedback'),
            AllowedFilter::partial('academic_feedback'),
            AllowedFilter::exact('created_by'),
            AllowedFilter::exact('created_at'),
        ];
    }

    public static function allowedSorts(): array
    {
        return [
            AllowedSort::field('id'),
            AllowedSort::field('student_attendance_id'),
            AllowedSort::field('created_at'),
        ];
    }

    public static function allowedIncludes(): array
    {
        return [
            'createdBy',
        ];
    }
}
