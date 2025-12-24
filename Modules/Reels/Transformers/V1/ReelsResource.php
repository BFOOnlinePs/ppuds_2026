<?php

namespace Modules\Reels\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Transformers\V1\UserResource;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class ReelsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'user_id'               => $this->user_id,
            'status'                => $this->status,
            'rejection_reason'      => $this->rejection_reason,
            'views_count'           => $this->views_count,
            'is_visible'            => $this->is_visible,
            'sort_order'            => $this->sort_order,
            'video_url'             => $this->getVideoAttribute(),
            'thumbnail_url'         => $this->getThumbnailAttribute(),
            'created_at'            => $this->created_at?->toIso8601String(),
            'user'                  => $this->whenLoaded('user', function () {
                return UserResource::make($this->user)->toArray(request());
            }),
        ];
    }
    public static function allowedFields(): array
    {
        return [
            'id',
            'user_id',
            'status',
            'rejection_reason',
            'views_count',
            'is_visible',
            'sort_order',
        ];
    }

    /**
     * تحديد الفلاتر المسموح بها.
     * نستخدم callback للحقول المترجمة لاستدعاء الـ scope المخصص في الموديل.
     */
    public static function allowedFilters(): array
    {
        return [
            AllowedFilter::exact('id'),
            AllowedFilter::exact('user_id'),
            AllowedFilter::exact('status'),
            AllowedFilter::exact('rejection_reason'),
            AllowedFilter::exact('views_count'),
            AllowedFilter::exact('is_visible'),
            AllowedFilter::exact('sort_order'),
        ];
    }

    public static function allowedSorts(): array
    {
        return [
            AllowedSort::field('id'),
            AllowedSort::field('sort_order'),
            AllowedSort::field('created_at')
        ];
    }
}
