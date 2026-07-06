<?php

namespace Modules\PPUDS\Transformers\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Branch\Transformers\V1\BranchResource;
use Modules\Core\Transformers\V1\UserResource;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class StudentCompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'registration_id' => $this->registration_id,
            'student_id' => $this->student_id,
            'company_id' => $this->company_id,
            'branch_id' => $this->branch_id,
            'department_id' => $this->department_id,
            'status' => $this->status,
            'attendance_days' => (int) ($this->attendance_days ?? 0),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,

            'registration' => RegistrationResource::make($this->whenLoaded('registration')),
            'student' => UserResource::make($this->whenLoaded('student')),
            'company' => CompanyResource::make($this->whenLoaded('company')),
            'branch' => BranchResource::make($this->whenLoaded('branch')),
            'department' => CompanyDepartmentResource::make($this->whenLoaded('department')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
        ];
    }

    public static function allowedFields(): array
    {
        return [
            'id',
            'registration_id',
            'student_id',
            'company_id',
            'branch_id',
            'department_id',
            'status',
            'created_by',
            'created_at',
        ];
    }

    public static function allowedFilters(): array
    {
        return [
            AllowedFilter::exact('id'),
            AllowedFilter::exact('registration_id'),
            AllowedFilter::exact('student_id'),
            AllowedFilter::exact('company_id'),
            AllowedFilter::exact('branch_id'),
            AllowedFilter::exact('status'),

            AllowedFilter::callback('company_supervisor', function (Builder $query, $value) {
                $query->whereHas('branch', function ($branchQuery) use ($value) {
                    $branchQuery->whereHas('departments', function ($departmentQuery) use ($value) {
                        $departmentQuery->where('user_id', $value);
                    });
                });
            }),

            AllowedFilter::callback('university_supervisor', function (Builder $query, $value) {
                $query->whereHas('registration', function ($registrationQuery) use ($value) {
                    $registrationQuery->where('supervisor_id', $value);
                });
            }),

            AllowedFilter::callback('supervisor_id', function (Builder $query, $value) {
                $query->whereHas('registration', function ($registrationQuery) use ($value) {
                    $registrationQuery->where('supervisor_id', $value);
                });
            }),

            AllowedFilter::callback('supercisor_id', function (Builder $query, $value) {
                $query->whereHas('registration', function ($registrationQuery) use ($value) {
                    $registrationQuery->where('supervisor_id', $value);
                });
            }),

            AllowedFilter::callback('student_name', function (Builder $query, $value) {
                $query->whereHas('student', function (Builder $q) use ($value) {
                    $q->where('name', 'like', '%' . $value . '%');
                });
            }),
            AllowedFilter::callback('company_name', function (Builder $query, $value) {
                $query->whereHas('company', function (Builder $q) use ($value) {
                    $q->whereTranslationLike('name', '%' . $value . '%');
                });
            }),

            AllowedFilter::callback('search', function (Builder $query, $value) {
                $query->where(function (Builder $q) use ($value) {
                    $q->whereHas('student', function (Builder $studentQuery) use ($value) {
                        $studentQuery
                            ->where('name', 'like', '%' . $value . '%')
                            ->orWhere('email', 'like', '%' . $value . '%')
                            ->orWhere('phone', 'like', '%' . $value . '%');
                    })
                        ->orWhereHas('student.studentProfile', function (Builder $profileQuery) use ($value) {
                            $profileQuery->where('student_number', 'like', '%' . $value . '%');
                        })
                        ->orWhereHas('company', function (Builder $companyQuery) use ($value) {
                            $companyQuery->whereTranslationLike('name', '%' . $value . '%');
                        });
                });
            }),

            AllowedFilter::callback('visit_date', function (Builder $query, $value) {
                $query->whereHas('fieldVisits', function (Builder $visitQuery) use ($value) {
                    $visitQuery->whereDate('visit_date', self::filterValue($value));
                });
            }),

            AllowedFilter::callback('without_visit_date', function (Builder $query, $value) {
                $query->whereDoesntHave('fieldVisits', function (Builder $visitQuery) use ($value) {
                    $visitQuery->whereDate('visit_date', self::filterValue($value));
                });
            }),

            AllowedFilter::callback('visit_date_from', function (Builder $query, $value) {
                self::whereHasFieldVisitInDateRange(
                    $query,
                    $value,
                    request()->input('filter.visit_date_to')
                );
            }),

            AllowedFilter::callback('visit_date_to', function (Builder $query, $value) {
                if (filled(request()->input('filter.visit_date_from'))) {
                    return;
                }

                self::whereHasFieldVisitInDateRange($query, null, $value);
            }),

            AllowedFilter::callback('semester', function (Builder $query, $value) {
                $query->whereHas('registration', function (Builder $registrationQuery) use ($value) {
                    $registrationQuery->where('semester', $value);
                });
            }),

            AllowedFilter::callback('year', function (Builder $query, $value) {
                $query->whereHas('registration', function (Builder $registrationQuery) use ($value) {
                    $registrationQuery->where('year', $value);
                });
            }),
        ];
    }

    public static function allowedSorts(): array
    {
        return [
            AllowedSort::field('id'),
            AllowedSort::field('created_at'),
            AllowedSort::field('status'),
        ];
    }

    public static function allowedIncludes(): array
    {
        return [
            'registration',
            'registration.course',
            'student',
            'student.user',
            'company',
            'branch',
            'department',
            'payments',
            'payments.currency',

            'branch.departments',
            'branch.workingHours',
            'branch.departments.user',
            'branch.departments.supervisors',
        ];
    }

    private static function whereHasFieldVisitInDateRange(Builder $query, mixed $from, mixed $to): void
    {
        $from = self::filterValue($from);
        $to = self::filterValue($to);

        $query->whereHas('fieldVisits', function (Builder $visitQuery) use ($from, $to) {
            if (filled($from)) {
                $visitQuery->whereDate('visit_date', '>=', $from);
            }

            if (filled($to)) {
                $visitQuery->whereDate('visit_date', '<=', $to);
            }
        });
    }

    private static function filterValue(mixed $value): mixed
    {
        return is_array($value) ? reset($value) : $value;
    }
}
