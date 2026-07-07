<?php

namespace Modules\PPUDS\Transformers\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Transformers\V1\UserResource;
use Modules\PPUDS\Services\NonComplianceReportService;
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
 * @OA\Property(
 * property="non_compliance",
 * type="object",
 * @OA\Property(property="total_days", type="integer", example=3),
 * @OA\Property(property="absence_days", type="integer", example=1),
 * @OA\Property(property="unexcused_absence_days", type="integer", example=1),
 * @OA\Property(property="late_days", type="integer", example=2),
 * @OA\Property(property="total_late_hours", type="number", format="float", example=3.5),
 * @OA\Property(property="max_late_hours", type="number", format="float", example=2),
 * @OA\Property(property="last_late_date", type="string", nullable=true, example="2026-07-07"),
 * @OA\Property(property="last_late_duration", type="string", nullable=true, example="2 Hours")
 * ),
 * @OA\Property(property="total_payment_amount", type="number", format="float", example=250.5),
 * @OA\Property(
 * property="total_payment_summary",
 * type="array",
 *
 * @OA\Items(
 * type="object",
 *
 * @OA\Property(property="currency_id", type="integer", example=1),
 * @OA\Property(property="currency_name", type="string", example="شيكل"),
 * @OA\Property(property="currency_code", type="string", example="ILS"),
 * @OA\Property(property="currency_symbol", type="string", example="₪"),
 * @OA\Property(property="total_payment_amount", type="number", format="float", example=150.5)
 * )
 * ),
 * @OA\Property(property="semester", type="string", example="First Semester"),
 * @OA\Property(property="year", type="integer", example=2024),
 * @OA\Property(property="created_at", type="string", format="date-time")
 * )
 */
class ReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $nonComplianceSummary = app(NonComplianceReportService::class)
            ->summary($this->resource, ...self::nonComplianceDateFilters($request));

        return [
            'id' => $this->id,

            'student_number' => $this->student?->studentProfile?->student_number,
            'student_name' => $this->student?->name,
            'gender' => $this->student?->studentProfile?->gender,
            'company_name' => $this->company?->name,

            // Computed/Appended Attributes
            'attendance_days' => (int) ($this->attendance_days ?? 0),
            'required_training_days' => $this->branch?->required_training_days,
            'attended_training_days' => $this->branch?->attended_training_days,
            'actual_working_hours' => $this->actual_working_hours,
            'non_compliance' => [
                'total_days' => (int) ($nonComplianceSummary['total_non_compliance_days'] ?? 0),
                'absence_days' => (int) ($nonComplianceSummary['total_absence_days'] ?? 0),
                'unexcused_absence_days' => (int) ($nonComplianceSummary['unexcused_absence_days'] ?? 0),
                'late_days' => (int) ($nonComplianceSummary['late_days'] ?? 0),
                'total_late_hours' => (float) ($nonComplianceSummary['total_late_hours'] ?? 0),
                'max_late_hours' => (float) ($nonComplianceSummary['max_late_hours'] ?? 0),
                'last_late_date' => $nonComplianceSummary['last_late_date'] ?? null,
                'last_late_duration' => $nonComplianceSummary['last_late_duration'] ?? null,
            ],
            'total_payment_amount' => (float) $this->total_payment_amount ?? 0,
            'total_payment_summary' => $this->totalPaymentSummary(),
            'total_attendance_leaves_days' => $this->total_attendance_leaves_days,

            // Registration Details
            'semester' => $this->registration?->semester->getLabel(),
            'year' => $this->registration?->year,

            'created_at' => $this->created_at,

            'student' => new UserResource($this->whenLoaded('student')),
            'company' => new CompanyResource($this->whenLoaded('company')),
        ];
    }

    private function totalPaymentSummary(): array
    {
        if (! $this->relationLoaded('payments')) {
            return [];
        }

        return $this->payments
            ->groupBy('currency_id')
            ->map(function ($payments) {
                $currency = $payments->first()?->currency;

                return [
                    'currency_id' => $payments->first()?->currency_id,
                    'currency_name' => $currency?->name,
                    'currency_code' => $currency?->code,
                    'currency_symbol' => $currency?->symbol,
                    'total_payment_amount' => (float) $payments->sum('payment_value'),
                ];
            })
            ->values()
            ->toArray();
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
                $value = self::filterValue($value);

                if (is_numeric($value)) {
                    $query->whereAttendanceDays('>=', (int) $value);
                }
            }),
            AllowedFilter::callback('attendance_days_to', function (Builder $query, $value) {
                $value = self::filterValue($value);

                if (is_numeric($value)) {
                    $query->whereAttendanceDays('<=', (int) $value);
                }
            }),

            AllowedFilter::callback('non_compliance', function (Builder $query, $value) {
                if (self::truthyFilterValue($value)) {
                    app(NonComplianceReportService::class)
                        ->applyNonComplianceFilter($query, ...self::nonComplianceDateFilters());
                }
            }),

            AllowedFilter::callback('minimum_late_hours', function (Builder $query, $value) {
                $value = self::filterValue($value);

                if (is_numeric($value)) {
                    app(NonComplianceReportService::class)
                        ->applyMinimumLateHoursFilter($query, $value, ...self::nonComplianceDateFilters());
                }
            }),

            AllowedFilter::callback('date', fn (Builder $query, $value): Builder => $query),
            AllowedFilter::callback('date_from', fn (Builder $query, $value): Builder => $query),
            AllowedFilter::callback('date_to', fn (Builder $query, $value): Builder => $query),
        ];
    }

    private static function filterValue(mixed $value): mixed
    {
        return is_array($value) ? reset($value) : $value;
    }

    private static function truthyFilterValue(mixed $value): bool
    {
        $value = self::filterValue($value);

        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    private static function nonComplianceDateFilters(?Request $request = null): array
    {
        $request ??= request();

        return [
            self::stringFilterValue($request->input('filter.date')),
            self::stringFilterValue($request->input('filter.date_from')),
            self::stringFilterValue($request->input('filter.date_to')),
        ];
    }

    private static function stringFilterValue(mixed $value): ?string
    {
        $value = self::filterValue($value);

        return filled($value) ? (string) $value : null;
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
