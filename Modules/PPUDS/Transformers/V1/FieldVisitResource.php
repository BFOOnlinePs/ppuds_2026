<?php

namespace Modules\PPUDS\Transformers\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

/**
 * @OA\Schema(
 * schema="FieldVisitResource",
 * title="Field Visit Resource",
 * description="Field Visit details",
 * @OA\Xml(name="FieldVisitResource"),
 * @OA\Property(property="id", type="integer", example=1),
 * @OA\Property(property="student_company_id", type="integer", example=5),
 * @OA\Property(property="supervisor_id", type="integer", example=3),
 * @OA\Property(property="visiting_place", type="string", example="Main Office"),
 * @OA\Property(property="visit_date", type="string", format="date", example="2024-05-20"),
 * @OA\Property(property="visit_time", type="string", format="time", example="09:00:00"),
 * @OA\Property(property="visit_duration", type="integer", example=60),
 * @OA\Property(property="notes", type="string", example="Everything went well"),
 * @OA\Property(property="attachments", type="array", @OA\Items(type="string", example="/storage/ppuds/field_visits/1/file.pdf")),
 * @OA\Property(
 * property="attachment_items",
 * type="array",
 * @OA\Items(
 * type="object",
 * @OA\Property(property="id", type="integer", example=10),
 * @OA\Property(property="url", type="string", example="/storage/ppuds/field_visits/1/file.pdf"),
 * @OA\Property(property="name", type="string", example="visit-report"),
 * @OA\Property(property="file_name", type="string", example="visit-report.pdf"),
 * @OA\Property(property="mime_type", type="string", example="application/pdf"),
 * @OA\Property(property="size", type="integer", example=204800),
 * @OA\Property(property="human_readable_size", type="string", example="200 KB"),
 * @OA\Property(property="is_image", type="boolean", example=false)
 * )
 * ),
 * @OA\Property(property="created_by", type="integer", example=123),
 * @OA\Property(property="created_at", type="string", format="date-time")
 * )
 */

class FieldVisitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'student_company_id' => $this->student_company_id,
            'supervisor_id'      => $this->supervisor_id,
            'visiting_place'     => $this->visiting_place,
            'visit_date'         => $this->visit_date,
            'visit_time'         => $this->visit_time,
            'visit_duration'     => $this->visit_duration,
            'notes'              => $this->notes,
            'attachments'        => $this->getAttachmentUrls(),
            'attachment_items'   => $this->getAttachmentItems(),
            'created_at'         => $this->created_at,
            'updated_at'         => $this->updated_at,
            'student_company'    => $this->whenLoaded('studentCompany'),
            'supervisor'         => $this->whenLoaded('supervisor'),
            'created_by'         => $this->whenLoaded('createdBy'),
        ];
    }

    public static function allowedFields(): array
    {
        return [
            'id',
            'student_company_id',
            'supervisor_id',
            'visiting_place',
            'visit_date',
            'visit_time',
            'visit_duration',
            'notes',
            'created_by',
            'created_at',
            'updated_at',
        ];
    }

    public static function allowedFilters(): array
    {
        return [
            AllowedFilter::exact('id'),
            AllowedFilter::exact('student_company_id'),
            AllowedFilter::callback('supervisor_id', function (Builder $query, $value) {
                self::whereHasUniversitySupervisor($query, $value);
            }),
            AllowedFilter::callback('supervisor_id', function (Builder $query, $value) {
                self::whereHasUniversitySupervisor($query, $value);
            }),
            AllowedFilter::exact('field_visit_supervisor_id', 'supervisor_id'),
            AllowedFilter::partial('visiting_place'),
            AllowedFilter::exact('visit_date'),
            AllowedFilter::exact('created_by'),
            AllowedFilter::exact('created_at'),
            AllowedFilter::exact('updated_at'),

            AllowedFilter::callback('university_supervisor', function (Builder $query, $value) {
                self::whereHasUniversitySupervisor($query, $value);
            }),

            AllowedFilter::callback('student_name', function (Builder $query, $value) {
                $query->whereHas('studentCompany.student', function (Builder $query) use ($value) {
                    $query->where('name', 'like', "%{$value}%");
                });
            }),

            AllowedFilter::callback('company_name', function (Builder $query, $value) {
                $query->whereHas('studentCompany.company', function (Builder $query) use ($value) {
                    $query->whereTranslationLike('name', "%{$value}%");
                });
            }),
        ];
    }

    private static function whereHasUniversitySupervisor(Builder $query, mixed $value): void
    {
        $supervisorIds = collect(Arr::wrap($value))
            ->filter(fn(mixed $supervisorId): bool => filled($supervisorId))
            ->map(fn(mixed $supervisorId): int => (int) $supervisorId)
            ->values();

        if ($supervisorIds->isEmpty()) {
            return;
        }

        $query->whereHas('studentCompany.registration', function (Builder $registrationQuery) use ($supervisorIds): void {
            if ($supervisorIds->count() === 1) {
                $registrationQuery->where('supervisor_id', $supervisorIds->first());

                return;
            }

            $registrationQuery->whereIn('supervisor_id', $supervisorIds->all());
        });
    }

    public static function allowedSorts(): array
    {
        return [
            AllowedSort::field('id'),
            AllowedSort::field('student_company_id'),
            AllowedSort::field('visit_date'),
            AllowedSort::field('visit_time'),
            AllowedSort::field('created_at'),
            AllowedSort::field('updated_at'),
        ];
    }

    public static function allowedIncludes(): array
    {
        return [
            'createdBy',
            'studentCompany',
            'studentCompany.student',
            'studentCompany.company',
            'supervisor',
            'studentCompany.registration',
            'media',
        ];
    }
}
