<?php

namespace Modules\PPUDS\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\QueryBuilder\AllowedSort;

/**
 * @OA\Schema(
 *     schema="PpudsBannerResource",
 *     title="PPUDS Banner Resource",
 *     description="Banner details",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="url", type="string", example="https://example.com/campaign"),
 *     @OA\Property(property="image", type="string", example="https://site.com/storage/ppuds/banners/img.png"),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 */
class BannerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'image' => $this->getFirstMediaUrl('banner_image'),
            'created_at' => $this->created_at,
        ];
    }

    public static function allowedFields(): array
    {
        return [
            'id',
            'created_at',
            'url',
        ];
    }

    public static function allowedSorts(): array
    {
        return [
            AllowedSort::field('id'),
            AllowedSort::field('created_at'),
        ];
    }
}
