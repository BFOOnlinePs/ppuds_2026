<?php

namespace Modules\Core\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Traits\Concerns\SelectsFieldsFromApi;
use Spatie\QueryBuilder\AllowedFilter;

class ActivityResource extends JsonResource
{
    use SelectsFieldsFromApi;
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'log_name' => $this->log_name,
            'description' => $this->description,
            'event' => $this->event,

            'properties' => $this->properties,

            'causer' => $this->whenLoaded('causer', function () {
                return [
                    'id' => $this->causer_id,
                    'type' => class_basename($this->causer_type),
                    'name' => $this->causer->name ?? null,
                ];
            }),

            'subject' => $this->whenLoaded('subject', function () {
                return [
                    'id' => $this->subject_id,
                    'type' => class_basename($this->subject_type),
                    'data' => $this->subject,
                ];
            }),

            'created_at' => $this->created_at->toIso8601String(),
        ];
    }

    public static function allowedFields(): array
    {
        return ['id', 'log_name', 'description', 'event', 'properties', 'causer_id', 'causer_type', 'subject_id', 'subject_type', 'created_at'];
    }

    public static function allowedSorts(): array
    {
        return ['id', 'log_name', 'event', 'created_at'];
    }

    public static function allowedFilters(): array
    {
        return [
            AllowedFilter::exact('id'),
            AllowedFilter::exact('log_name'),
            AllowedFilter::exact('event'),
            AllowedFilter::scope('causer_id'),
            AllowedFilter::scope('causer_type'),
            AllowedFilter::scope('subject_id'),
            AllowedFilter::scope('subject_type'),
            AllowedFilter::scope('created_at_between'),
        ];
    }

    public static function allowedIncludes(): array
    {
        return ['causer', 'subject'];
    }
}
