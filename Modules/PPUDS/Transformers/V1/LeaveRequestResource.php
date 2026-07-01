<?php

namespace Modules\PPUDS\Transformers\V1;

use Doctrine\DBAL\Query;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;


/**
 * @OA\Schema(
 * schema="LeaveRequestResource",
 * title="Leave Request Resource",
 * description="Leave Request details",
 * @OA\Xml(name="LeaveRequestResource"),
 * @OA\Property(property="id", type="integer", example=1),
 * @OA\Property(property="student_company_id", type="integer", example=5),
 * @OA\Property(property="supervisor_id", type="integer", example=3),
 * @OA\Property(property="type", type="string", example="leave"),
 * @OA\Property(property="start_at", type="string", format="date-time", example="2024-05-20 08:00:00"),
 * @OA\Property(property="end_at", type="string", format="date-time", example="2024-05-20 10:00:00"),
 * @OA\Property(property="reason", type="string", example="Medical appointment"),
 * @OA\Property(property="company_approval", type="boolean", example=true),
 * @OA\Property(property="university_approval", type="boolean", example=false),
 * @OA\Property(property="rejection_reason", type="string", example=null),
 * @OA\Property(property="attachment_file", type="string", example="/uploads/attachment_1.jpg"),
 * @OA\Property(property="created_by", type="integer", example=123),
 * @OA\Property(property="created_at", type="string", format="date-time")
 * )
 */

class LeaveRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                            => $this->id,
            'student_company_id'            => $this->student_company_id,
            'supervisor_id'                  => $this->studentCompany?->registration?->supervisor_id,
            'type'                          => $this->type,
            'start_at'                      => $this->start_at,
            'end_at'                        => $this->end_at,
            'reason'                        => $this->reason,
            'company_approval'              => $this->company_approval,
            'university_approval'           => $this->university_approval,
            'company_supervisor_id'          => $this->company_supervisor_id,
            'university_supervisor_id'       => $this->university_supervisor_id,
            'company_supervisor_comment'    => $this->company_supervisor_comment,
            'university_supervisor_comment' => $this->university_supervisor_comment,
            'rejection_reason'              => $this->rejection_reason,
            'attachment_file'               => $this->getAttachmentFileAttribute(),
            'student_company'               => $this->whenLoaded('studentCompany'),
            'created_by'                    => $this->whenLoaded('createdBy'),
        ];
    }

    public static function allowedFields(): array
    {
        return [
            'id',
            'student_company_id',
            'type',
            'start_at',
            'end_at',
            'reason',
            'company_approval',
            'university_approval',
            'company_supervisor_id',
            'university_supervisor_id',
            'rejection_reason',
            'created_by',
        ];
    }

    public static function allowedFilters(): array
    {
        return [
            AllowedFilter::exact('id'),
            AllowedFilter::exact('student_company_id'),
            AllowedFilter::partial('reason'),
            AllowedFilter::exact('company_approval'),
            AllowedFilter::exact('university_approval'),
            AllowedFilter::exact('created_by'),
            AllowedFilter::exact('created_at'),
            AllowedFilter::exact('company_supervisor_id'),
            AllowedFilter::exact('university_supervisor_id'),

            AllowedFilter::callback('company_supervisor', function (Builder $query, $value) {
                $query->whereHas('studentCompany', function ($query) use ($value) {
                    $query->whereHas('branch', function ($branchQuery) use ($value) {
                        $branchQuery->whereHas('departments', function ($departmentQuery) use ($value) {
                            $departmentQuery->where('user_id', $value);
                        });
                    });
                });
            }),

            AllowedFilter::callback('university_supervisor', function (Builder $query, $value) {
                $query->whereHas('studentCompany.registration', function (Builder $registrationQuery) use ($value) {
                    $registrationQuery->where('supervisor_id', $value);
                });
            }),

            AllowedFilter::callback('supervisor_id', function (Builder $query, $value) {
                $query->whereHas('studentCompany.registration', function (Builder $registrationQuery) use ($value) {
                    $registrationQuery->where('supervisor_id', $value);
                });
            }),

            AllowedFilter::callback('student_name', function (Builder $query, $value) {
                $query->whereHas('studentCompany.student', function (Builder $q) use ($value) {
                    $q->where('name', 'like', '%' . $value . '%');
                });
            }),
            AllowedFilter::callback('company_name', function (Builder $query, $value) {
                $query->whereHas('studentCompany.company', function (Builder $q) use ($value) {
                    $q->whereTranslationLike('name', '%' . $value . '%');
                });
            })
        ];
    }

    public static function allowedSorts(): array
    {
        return [
            AllowedSort::field('id'),
            AllowedSort::field('student_company_id'),
            AllowedSort::field('start_at'),
            AllowedSort::field('end_at'),
            AllowedSort::field('created_at'),
        ];
    }

    public static function allowedIncludes(): array
    {
        return [
            'createdBy',
            'studentCompany',
            'studentCompany.registration',
            'studentCompany.registration.supervisor',
            'studentCompany.student',
            'studentCompany.company',
            'companySupervisor',
            'universitySupervisor',
        ];
    }
}
