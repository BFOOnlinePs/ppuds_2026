<?php

namespace Modules\PPUDS\Transformers\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
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
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,

            // تحميل العلاقات عند الطلب
            'registration' => $this->whenLoaded('registration'), // يمكن تفصيلها لريسورس آخر
            'student' => $this->whenLoaded('student'),      // Student Resource
            'company' => new CompanyResource($this->whenLoaded('company')),
            'branch' => $this->whenLoaded('branch'),
            'department' => new CompanyDepartmentResource($this->whenLoaded('department')),
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
            AllowedFilter::exact('status'),

            AllowedFilter::callback('company_supervisor', function (Builder $query, $value) {

                $query->whereHas('branch', function ($branchQuery) use ($value) {
                    $branchQuery->whereHas('departments', function ($departmentQuery) use ($value) {
                        $departmentQuery->where('user_id', $value);
                    });
                });
            }),

            AllowedFilter::callback('student_name', function (Builder $query, $value) {
                $query->whereHas('student', function (Builder $q) use ($value) {
                    $q->where('name', 'like', '%'.$value.'%');
                });
            }),
            AllowedFilter::callback('company_name', function (Builder $query, $value) {
                $query->whereHas('company', function (Builder $q) use ($value) {
                    $q->whereTranslationLike('name', '%'.$value.'%');
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
        ];
    }
}
