<?php

namespace Modules\PPUDS\Transformers\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Transformers\V1\UserResource;
use Modules\PPUDS\Enums\AttendanceStatus;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

/**
 * @OA\Schema(
 * schema="ReportResource",
 * title="Report Resource",
 * description="Student Company Attendance Report details",
 *
 * @OA\Xml(name="ReportResource"),
 *
 * @OA\Property(property="id", type="integer", example=1),
 * @OA\Property(property="student_number", type="string", example="123456"),
 * @OA\Property(property="student_name", type="string", example="أحمد محمد"),
 * @OA\Property(property="gender", type="string", example="Male"),
 * @OA\Property(property="company_name", type="string", example="شركة الأمل"),
 * @OA\Property(property="attendance_days", type="integer", example=15),
 * @OA\Property(property="required_training_days", type="integer", example=40),
 * @OA\Property(property="attended_training_days", type="integer", example=15),
 * @OA\Property(property="actual_working_hours", type="number", format="float", example=120.5),
 * @OA\Property(property="semester", type="string", example="First Semester"),
 * @OA\Property(property="year", type="integer", example=2024),
 * @OA\Property(property="created_at", type="string", format="date-time")
 * )
 */
class ReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                        => $this->id,

            'student_number'            => $this->student?->studentProfile?->student_number,
            'student_name'              => $this->student?->name,
            'gender'                    => $this->student?->studentProfile?->gender,
            'company_name'              => $this->company?->name,

            // Computed/Appended Attributes
            'attendance_days'           => $this->attendance_days,
            'required_training_days'    => $this->branch?->required_training_days,
            'attended_training_days'    => $this->branch?->attended_training_days,
            'actual_working_hours'      => $this->actual_working_hours,

            // Registration Details
            'semester'                  => $this->registration?->semester->getLabel(),
            'year'                      => $this->registration?->year,

            'created_at'                => $this->created_at,

            'student'                   => new UserResource($this->whenLoaded('student')),
            'company'                   => new CompanyResource($this->whenLoaded('company')),
        ];
    }

    public static function allowedFields(): array
    {
        return [
            'id', 'created_at',
        ];
    }

    public static function allowedFilters(): array
    {
        return [
            AllowedFilter::exact('id'),

            AllowedFilter::callback('student_number', function (Builder $query, $value) {
                $query->whereHas('student.studentProfile', fn ($sq) => $sq->where('student_number', 'like', "%{$value}%"))
                    ->orWhereHas('student', fn ($sq) => $sq->where('name', 'like', "%{$value}%"));
            }),

            AllowedFilter::exact('student_gender', 'student.studentProfile.gender'),
            AllowedFilter::exact('company_id', 'company_id'),
            AllowedFilter::exact('year', 'registration.year'),
            AllowedFilter::exact('semester_type', 'registration.semester'),

            // فلتر أيام الحضور (من - إلى)
            AllowedFilter::callback('attendance_days_from', function (Builder $query, $value) {
                $query->whereHas('attendances', function ($subQ) {
                    $subQ->where('status', AttendanceStatus::UNDETERMINED);
                }, '>=', $value);
            }),
            AllowedFilter::callback('attendance_days_to', function (Builder $query, $value) {
                $query->whereHas('attendances', function ($subQ) {
                    $subQ->where('status', AttendanceStatus::UNDETERMINED);
                }, '<=', $value);
            }),
        ];
    }

    public static function allowedSorts(): array
    {
        return [
            AllowedSort::field('created_at'),
            AllowedSort::field('id'),
        ];
    }

    public static function allowedIncludes(): array
    {
        return [
            'createdBy',
        ];
    }
}
