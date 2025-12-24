<?php

namespace Modules\Content\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Modules\Content\Entities\FaqCategory;
use Modules\Content\Transformers\V1\FaqCategoryResource;
use Modules\Core\Traits\ApiResponse;
use Spatie\QueryBuilder\QueryBuilder;

class FaqCategoriesController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     *   path="/api/v1/content/faq-categories",
     *   summary="Get FAQ Categories",
     *   tags={"Content"},
     *   security={{"sanctum": {}}},
     *   @OA\Parameter(
     *     name="Accept-Language",
     *     in="header",
     *     required=true,
     *     @OA\Schema(type="string", default="ar")
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Success",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="status", type="boolean", example=true),
     *       @OA\Property(property="message", type="string"),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(
     *           type="object",
     *           @OA\Property(property="id", type="integer", example=1),
     *           @OA\Property(property="slug", type="string", example="general"),
     *           @OA\Property(property="name", type="string", example="أسئلة عامة")
     *         )
     *       ),
     *       @OA\Property(property="meta", type="object"),
     *       @OA\Property(property="links", type="object")
     *     )
     *   )
     * )
     */

    public function index()
    {
        $defaultPerPage = config('core.pagination.per_page', 10);
        $maxPerPage = config('core.pagination.max_per_page', 100);
        $perPage = min(request('per_page', $defaultPerPage), $maxPerPage);

        $categories = QueryBuilder::for(FaqCategory::class)
            ->allowedFields(FaqCategoryResource::allowedFields())
            ->allowedFilters(FaqCategoryResource::allowedFilters())
            ->allowedSorts(FaqCategoryResource::allowedSorts())
            ->allowedIncludes(['faqs'])
            ->defaultSort('sort_order')
            ->where('is_active', true)
            ->paginate($perPage)
            ->appends(request()->query());

        return $this->successResponse(
            FaqCategoryResource::collection($categories),
            __('FAQ Categories retrieved successfully')
        );
    }

    /**
     * @OA\Get(
     *   path="/api/v1/content/faq-categories/{slug}",
     *   summary="Get single FAQ Category",
     *   tags={"Content"},
     *   security={{"sanctum": {}}},
     *   @OA\Parameter(
     *     name="Accept-Language",
     *     in="header",
     *     required=true,
     *     @OA\Schema(type="string", default="ar")
     *   ),
     *   @OA\Parameter(
     *     name="slug",
     *     in="path",
     *     required=true,
     *     @OA\Schema(type="string")
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Success",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="status", type="boolean", example=true),
     *       @OA\Property(property="message", type="string"),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", example=1),
     *         @OA\Property(property="slug", type="string", example="general"),
     *         @OA\Property(property="name", type="string", example="أسئلة عامة")
     *       )
     *     )
     *   )
     * )
     */

    public function show($slug)
    {
        $category = QueryBuilder::for(FaqCategory::class)
            ->allowedFields(FaqCategoryResource::allowedFields())
            ->allowedIncludes(['faqs'])
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (!$category) {
            return $this->errorResponse(__('Category not found'), 404);
        }

        return $this->successResponse(
            new FaqCategoryResource($category),
            __('Category retrieved successfully')
        );
    }
}
