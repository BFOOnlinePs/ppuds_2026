<?php

namespace Modules\PPUDS\Transformers\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Modules\PPUDS\Entities\StudentProfile;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

/**
 * @OA\Schema(
 * schema="StudentAttachmentResource",
 * title="Student Attachment Resource",
 * description="Optional attachment attached to a student profile",
 * @OA\Xml(name="StudentAttachmentResource"),
 * @OA\Property(property="id", type="integer", example=10),
 * @OA\Property(property="student_id", type="integer", example=42),
 * @OA\Property(property="student_profile_id", type="integer", example=7),
 * @OA\Property(property="name", type="string", example="Training agreement"),
 * @OA\Property(property="file_name", type="string", example="20260831120000_a1b2c3d4_training-agreement.pdf"),
 * @OA\Property(property="mime_type", type="string", example="application/pdf"),
 * @OA\Property(property="size", type="integer", example=204800),
 * @OA\Property(property="human_readable_size", type="string", example="200 KB"),
 * @OA\Property(property="is_image", type="boolean", example=false),
 * @OA\Property(property="url", type="string", example="/storage/ppuds/student_profiles/10/training-agreement.pdf"),
 * @OA\Property(property="created_at", type="string", format="date-time")
 * )
 */
class StudentAttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'student_id'          => $this->model?->user_id,
            'student_profile_id'  => $this->model_id,
            'name'                => $this->name,
            'file_name'           => $this->file_name,
            'mime_type'           => $this->mime_type,
            'size'                => $this->size,
            'human_readable_size' => $this->human_readable_size,
            'is_image'            => str_starts_with((string) $this->mime_type, 'image/'),
            'url'                 => $this->getUrl(),
            'student_profile'     => $this->whenLoaded('model'),
            'created_at'          => $this->created_at,
        ];
    }

    public static function allowedFields(): array
    {
        return [
            'id',
            'model_id',
            'name',
            'file_name',
            'mime_type',
            'disk',
            'size',
            'created_at',
        ];
    }

    public static function allowedFilters(): array
    {
        return [
            AllowedFilter::exact('id'),
            AllowedFilter::exact('student_profile_id', 'model_id'),
            AllowedFilter::partial('name'),
            AllowedFilter::partial('file_name'),
            AllowedFilter::exact('mime_type'),

            AllowedFilter::callback('student_id', function (Builder $query, $value) {
                $studentIds = collect(Arr::wrap($value))
                    ->filter(fn (mixed $studentId): bool => filled($studentId))
                    ->map(fn (mixed $studentId): int => (int) $studentId)
                    ->values();

                if ($studentIds->isEmpty()) {
                    return;
                }

                $query->whereIn(
                    'model_id',
                    StudentProfile::query()
                        ->whereIn('user_id', $studentIds->all())
                        ->select('id')
                );
            }),

            AllowedFilter::callback('student_number', function (Builder $query, $value) {
                $query->whereIn(
                    'model_id',
                    StudentProfile::query()
                        ->where('student_number', $value)
                        ->select('id')
                );
            }),
        ];
    }

    public static function allowedSorts(): array
    {
        return [
            AllowedSort::field('id'),
            AllowedSort::field('name'),
            AllowedSort::field('size'),
            AllowedSort::field('created_at'),
        ];
    }

    public static function allowedIncludes(): array
    {
        return ['model'];
    }
}
