<?php

namespace Modules\PPUDS\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Transformers\V1\UserResource;

/**
 * @OA\Schema(
 * schema="AbsenceReportResource",
 * title="Absence Report Resource",
 * description="Per-student absence summary for a training placement, including the specific absence dates",
 *
 * @OA\Xml(name="AbsenceReportResource"),
 *
 * @OA\Property(property="id", type="integer", example=1),
 * @OA\Property(property="student_number", type="string", example="123456"),
 * @OA\Property(property="student_name", type="string", example="أحمد محمد"),
 * @OA\Property(property="company_name", type="string", example="شركة الأمل"),
 * @OA\Property(property="branch_name", type="string", example="الفرع الرئيسي"),
 * @OA\Property(property="required_working_days", type="integer", example=40),
 * @OA\Property(property="attendance_days", type="integer", example=35),
 * @OA\Property(property="actual_working_hours", type="number", format="float", example=210.5),
 * @OA\Property(property="total_absence_days", type="integer", example=5),
 * @OA\Property(property="excused_absence_days", type="integer", example=2),
 * @OA\Property(property="unexcused_absence_days", type="integer", example=3),
 * @OA\Property(property="actual_absence_days", type="integer", example=5),
 * @OA\Property(property="leave_request_days", type="integer", example=2),
 * @OA\Property(property="excused_absence_dates", type="array", @OA\Items(type="string", format="date", example="2026-07-12")),
 * @OA\Property(property="unexcused_absence_dates", type="array", @OA\Items(type="string", format="date", example="2026-07-15")),
 * @OA\Property(property="semester", type="string", example="First Semester"),
 * @OA\Property(property="year", type="integer", example=2026)
 * )
 */
class AbsenceReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $summary = $this->absence_summary ?? [];

        return [
            'id' => $this->id,

            'student_number' => $this->student?->studentProfile?->student_number,
            'student_name' => $this->student?->name,
            'company_name' => $this->company?->name,
            'branch_name' => $this->branch?->name,

            'required_working_days' => (int) ($summary['required_working_days'] ?? 0),
            'attendance_days' => (int) ($summary['attendance_days'] ?? 0),
            'actual_working_hours' => $this->actual_working_hours,
            'total_absence_days' => (int) ($summary['total_absence_days'] ?? 0),
            'excused_absence_days' => (int) ($summary['excused_absence_days'] ?? 0),
            'unexcused_absence_days' => (int) ($summary['unexcused_absence_days'] ?? 0),
            'actual_absence_days' => (int) ($summary['actual_absence_days'] ?? 0),
            'leave_request_days' => (int) ($summary['leave_request_days'] ?? 0),
            'excused_absence_dates' => $summary['excused_absence_dates'] ?? [],
            'unexcused_absence_dates' => $summary['unexcused_absence_dates'] ?? [],

            'semester' => $this->registration?->semester?->getLabel(),
            'year' => $this->registration?->year,

            'student' => new UserResource($this->whenLoaded('student')),
            'company' => new CompanyResource($this->whenLoaded('company')),
        ];
    }
}
