<?php

namespace Modules\Core\Transformers\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\QueryBuilder\AllowedFilter;

class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => class_basename($this->type),
            'title' => $this->data['title'] ?? null,
            'message' => $this->data['message'] ?? null,
            'url' => $this->data['url'] ?? null,
            'icon' => $this->data['icon'] ?? null,
            'color' => $this->data['color'] ?? null,
            'image' => $this->data['image'] ?? null,
            'is_read' => $this->read_at !== null,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    public static function allowedFields(): array
    {
        return ['id', 'type', 'read_at', 'created_at'];
    }

    public static function allowedSorts(): array
    {
        return ['id', 'read_at', 'created_at'];
    }

    public static function allowedFilters(): array
    {
        return [
            AllowedFilter::exact('id'),
            AllowedFilter::exact('type'),
            AllowedFilter::callback('unread', function (Builder $query, $value) {
                $value ? $query->whereNull('read_at') : $query->whereNotNull('read_at');
            }),
            AllowedFilter::callback('created_at', function (Builder $query, $value) {
                $query->whereDate('created_at', $value);
            }),
        ];
    }

    public static function allowedIncludes(): array
    {
        return [];
    }
}
