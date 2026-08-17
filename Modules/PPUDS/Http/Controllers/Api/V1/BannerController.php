<?php

namespace Modules\PPUDS\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Modules\Core\Traits\ApiResponse;
use Modules\PPUDS\Entities\Banner;
use Modules\PPUDS\Transformers\V1\BannerResource;
use Spatie\QueryBuilder\QueryBuilder;

class BannerController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     *     path="/api/v1/ppuds/banners",
     *     summary="Get all active banners",
     *     description="Retrieve a list of all active banners, with the link localized to the Accept-Language header",
     *     tags={"Banners"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language header (ar or en)",
     *
     *         @OA\Schema(type="string", default="ar", example="en")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Banners retrieved successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Banners retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(ref="#/components/schemas/PpudsBannerResource")
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        $defaultPerPage = config('core.pagination.per_page', 10);
        $maxPerPage = config('core.pagination.max_per_page', 100);
        $perPage = min(request('per_page', $defaultPerPage), $maxPerPage);

        $banners = QueryBuilder::for(Banner::class)
            ->allowedFields(BannerResource::allowedFields())
            ->allowedSorts(BannerResource::allowedSorts())
            ->active()
            ->with(['media', 'translations'])
            ->latest()
            ->paginate($perPage)
            ->appends(request()->query());

        return $this->successResponse(
            BannerResource::collection($banners),
            __('Banners retrieved successfully')
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/ppuds/banners/{banner}",
     *     summary="Get a single banner",
     *     tags={"Banners"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(
     *         name="banner",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Banner retrieved successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Banner retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/PpudsBannerResource")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Banner not found")
     * )
     */
    public function show(Banner $banner)
    {
        if (! $banner->active) {
            return $this->errorResponse(__('Banner not found'), 404);
        }

        $banner->load(['media', 'translations']);

        return $this->successResponse(
            new BannerResource($banner),
            __('Banner retrieved successfully')
        );
    }
}
