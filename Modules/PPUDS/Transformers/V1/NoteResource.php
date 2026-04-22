<?php

namespace Modules\PPUDS\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Transformers\V1\UserResource;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

/**
 * @OA\Schema(
 * schema="NoteResource",
 * title="Note Resource",
 * description="Note details",
 * @OA\Xml(name="NoteResource"),
 * @OA\Property(property="id", type="integer", example=1),
 * @OA\Property(property="name", type="string", example="ملاحظة تدريبية"),
 * @OA\Property(property="content", type="string", example="تفاصيل الملاحظة هنا..."),
 * @OA\Property(property="note_date", type="string", format="date", example="2026-03-01"),
 * @OA\Property(property="is_pinned", type="boolean", example=true),
 * @OA\Property(property="image", type="string", example="https://site.com/storage/1/note.png")
 * )
 */
class NoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'content'       => $this->content,
            'note_date'     => $this->note_date?->format('Y-m-d'),
            'is_pinned'     => (bool) $this->is_pinned,
            'image'         => $this->getFirstMediaUrl('note_image'),
            'user_id'       => $this->user_id,
            'user'          => UserResource::make($this->whenLoaded('user')),
            'created_at'    => $this->created_at,
        ];
    }

    public static function allowedFields(): array
    {
        return [
            'id', 'name', 'content', 'note_date', 'is_pinned', 'user_id', 'created_at'
        ];
    }

    public static function allowedFilters(): array
    {
        return [
            AllowedFilter::callback('name', fn (Builder $query, $value) => $query->whereTranslationLike('name', "%{$value}%")),
            AllowedFilter::callback('content', fn (Builder $query, $value) => $query->whereTranslationLike('content', "%{$value}%")),

            AllowedFilter::exact('is_pinned'),
            AllowedFilter::exact('user_id'),
            AllowedFilter::callback('date_from', fn (Builder $query, $value) => $query->whereDate('note_date', '>=', $value)),
            AllowedFilter::callback('date_to', fn (Builder $query, $value) => $query->whereDate('note_date', '<=', $value)),
        ];
    }

    public static function allowedSorts(): array
    {
        return [
            AllowedSort::field('id'),
            AllowedSort::field('note_date'),
            AllowedSort::field('is_pinned'),
            AllowedSort::field('created_at'),
        ];
    }

    public static function allowedIncludes(): array
    {
        return [
            'user',
        ];
    }
}
