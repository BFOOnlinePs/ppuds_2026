<?php

namespace Modules\PPUDS\Transformers\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\AllowedSort;

class StudentAttendanceResource extends JsonResource
{
    private const OUTSIDE_WORK_RANGE_DISTANCE_METERS = 200;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_company_id' => $this->student_company_id,
            'attendance_date' => $this->attendance_date?->format('Y-m-d'),

            'check_in' => [
                'time' => $this->check_in?->format('H:i:s'),
                'latitude' => $this->check_in_latitude,
                'longitude' => $this->check_in_longitude,
            ],

            'check_out' => [
                'time' => $this->check_out?->format('H:i:s'),
                'latitude' => $this->check_out_latitude,
                'longitude' => $this->check_out_longitude,
            ],

            'status' => $this->status,
            'description' => $this->description,
            'is_outside_work_range' => $this->isOutsideWorkRange(),
            'distance_from_branch_meters' => $this->distanceFromBranchMeters(),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),

            'student_company' => new StudentCompanyResource($this->whenLoaded('studentCompany')),
            'creator' => $this->whenLoaded('createdBy'),
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
            AllowedFilter::callback('student_name', function (Builder $query, $value) {
                $query->whereHas('studentCompany.student', function ($q) use ($value) {
                    $q->where('name', 'like', "%{$value}%");
                });
            }),
            AllowedFilter::callback('company_name', function (Builder $query, $value) {
                $query->whereHas('studentCompany.company', function (Builder $q) use ($value) {
                    $q->whereTranslationLike('name', "%{$value}%");
                });
            }),
            AllowedFilter::callback('attendance_date', fn(Builder $query, $value) => $query->whereDate('attendance_date', self::filterValue($value))),
            AllowedFilter::callback('attendance_date_from', function (Builder $query, $value) {
                self::whereAttendanceDateInRange(
                    $query,
                    $value,
                    request()->input('filter.attendance_date_to')
                );
            }),
            AllowedFilter::callback('attendance_date_to', function (Builder $query, $value) {
                if (filled(request()->input('filter.attendance_date_from'))) {
                    return;
                }

                self::whereAttendanceDateInRange($query, null, $value);
            }),

            AllowedFilter::callback('today_present_students', function (Builder $query, $value) {
                if (! self::truthyFilterValue($value)) {
                    return;
                }

                $query
                    ->whereDate('attendance_date', self::todayFilterDate())
                    ->whereNotNull('check_in');
            }),

            AllowedFilter::callback('today_absent_students', function (Builder $query): Builder {
                // Handled by StudentAttendanceController because absent students do not have attendance rows.
                return $query;
            }),

            AllowedFilter::callback('student_id', function (Builder $query, $value) {
                $query->whereHas('studentCompany.registration', function ($query) use ($value) {
                    $query->where('student_id', $value);
                });
            }),
            AllowedFilter::callback('company_id', function (Builder $query, $value) {
                $query->whereHas('studentCompany.registration', function ($query) use ($value) {
                    $query->where('company_id', $value);
                });
            }),
            AllowedFilter::callback('supervisor_id', function ($query, $value) {
                $query->whereHas('studentCompany.registration', function ($q) use ($value) {
                    $q->where('supervisor_id', $value);
                });
            }),
            AllowedFilter::callback('semester', function (Builder $query, $value) {
                $query->whereHas('studentCompany.registration', function ($query) use ($value) {
                    $query->where('semester', $value);
                });
            }),
            AllowedFilter::callback('year', function (Builder $query, $value) {
                $query->whereHas('studentCompany.registration', function ($query) use ($value) {
                    $query->where('year', $value);
                });
            }),
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
        return ['studentCompany', 'studentCompany.student', 'createdBy', 'studentCompany.company', 'studentCompany.registration', 'studentCompany.branch'];
    }

    private function distanceFromBranchMeters(): ?int
    {
        $branch = $this->studentCompany?->branch;

        if (
            blank($this->check_in_latitude)
            || blank($this->check_in_longitude)
            || blank($branch?->latitude)
            || blank($branch?->longitude)
        ) {
            return null;
        }

        return self::haversineDistanceInMeters(
            (float) $this->check_in_latitude,
            (float) $this->check_in_longitude,
            (float) $branch->latitude,
            (float) $branch->longitude,
        );
    }

    private function isOutsideWorkRange(): bool
    {
        $distance = $this->distanceFromBranchMeters();

        return $distance !== null && $distance > self::OUTSIDE_WORK_RANGE_DISTANCE_METERS;
    }

    private static function haversineDistanceInMeters(float $latitudeA, float $longitudeA, float $latitudeB, float $longitudeB): int
    {
        $earthRadius = 6371000;
        $latitudeDelta = deg2rad($latitudeB - $latitudeA);
        $longitudeDelta = deg2rad($longitudeB - $longitudeA);

        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($latitudeA)) * cos(deg2rad($latitudeB))
            * sin($longitudeDelta / 2) ** 2;

        return (int) round($earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    private static function whereAttendanceDateInRange(Builder $query, mixed $from, mixed $to): void
    {
        $from = self::filterValue($from);
        $to = self::filterValue($to);

        if (filled($from)) {
            $query->whereDate('attendance_date', '>=', $from);
        }

        if (filled($to)) {
            $query->whereDate('attendance_date', '<=', $to);
        }
    }

    private static function filterValue(mixed $value): mixed
    {
        return is_array($value) ? reset($value) : $value;
    }

    private static function truthyFilterValue(mixed $value): bool
    {
        return filter_var(self::filterValue($value), FILTER_VALIDATE_BOOLEAN);
    }

    private static function todayFilterDate(): string
    {
        return request()->input('filter.attendance_date') ?: now()->toDateString();
    }
}
