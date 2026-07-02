<?php

namespace Modules\PPUDS\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;


/**
 * @OA\Schema(
 * schema="StudentAttendanceReportResource",
 * title="Student Attendance Report Resource",
 * description="Student Attendance Report details",
 * @OA\Xml(name="StudentAttendanceReportResource"),
 * @OA\Property(property="id", type="integer", example=1),
 * @OA\Property(property="student_attendance_id", type="integer", example=5),
 * @OA\Property(property="report_text", type="string", example="Today I learned about..."),
 * @OA\Property(property="company_feedback", type="string", example="Excellent work"),
 * @OA\Property(property="academic_feedback", type="string", example="Keep it up"),
 * @OA\Property(property="submit_latitude", type="number", format="float", example=31.9038),
 * @OA\Property(property="submit_longitude", type="number", format="float", example=35.2034),
 * @OA\Property(property="file_report", type="string", example="/uploads/report_1.jpg"),
 * @OA\Property(property="created_by", type="integer", example=123),
 * @OA\Property(property="created_at", type="string", format="date-time")
 * )
 */

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
            'created_at'                => $this->created_at,
            'student_attendance'        => new StudentAttendanceResource($this->whenLoaded('studentAttendance')),
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
            AllowedFilter::scope('today'),

            AllowedFilter::callback('date_from', function ($query, $value) {
                $query->whereDate('created_at', '>=', $value);
            }),
            AllowedFilter::callback('date_to', function ($query, $value) {
                $query->whereDate('created_at', '<=', $value);
            }),

            AllowedFilter::callback('student_company_id', function ($query, $value) {
                $query->whereHas('studentAttendance', function ($q) use ($value) {
                    $q->where('student_company_id', $value);
                });
            }),

            AllowedFilter::callback('supervisor_id', function ($query, $value) {
                $query->whereHas('studentAttendance.studentCompany.registration', function ($q) use ($value) {
                    $q->where('supervisor_id', $value);
                });
            }),
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
            'studentAttendance',
            'studentAttendance.studentCompany',
            'studentAttendance.studentCompany.student',
            'studentAttendance.studentCompany.company',
            'studentAttendance.studentCompany.registration',
        ];
    }
}
