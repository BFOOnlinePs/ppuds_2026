<?php

namespace Modules\Content\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Modules\Content\Entities\Faq;
use Modules\Content\Transformers\V1\FaqResource;
use Modules\Core\Traits\ApiResponse;
use Spatie\QueryBuilder\QueryBuilder;

class FaqsController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     *   path="/api/v1/content/faqs",
     *   summary="Get all FAQs",
     *   tags={"Content"},
     *   security={{"sanctum": {}}},
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
     *           @OA\Property(property="question", type="string", example="ما هي سياسة الإرجاع؟"),
     *           @OA\Property(property="answer", type="string", example="يمكنك الإرجاع خلال 14 يوم")
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

        $faqs = QueryBuilder::for(Faq::class)
            ->allowedFields(FaqResource::allowedFields())
            ->allowedFilters(FaqResource::allowedFilters())
            ->allowedSorts(FaqResource::allowedSorts())
            ->defaultSort('sort_order')
            ->where('is_active', true)
            ->paginate($perPage)
            ->appends(request()->query());

        return $this->successResponse(
            FaqResource::collection($faqs),
            __('FAQs retrieved successfully')
        );
    }

    /**
     * @OA\Get(
     *   path="/api/v1/content/faqs/{id}",
     *   summary="Get single FAQ",
     *   tags={"Content"},
     *   security={{"sanctum": {}}},
     *   @OA\Parameter(
     *     name="id",
     *     in="path",
     *     required=true,
     *     @OA\Schema(type="integer")
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
     *         @OA\Property(property="question", type="string"),
     *         @OA\Property(property="answer", type="string")
     *       )
     *     )
     *   )
     * )
     */

    public function show($id)
    {
        $faq = QueryBuilder::for(Faq::class)
            ->allowedFields(FaqResource::allowedFields())
            ->where('id', $id)
            ->where('is_active', true)
            ->first();

        if (!$faq) {
            return $this->errorResponse(__('FAQ not found'), 404);
        }

        return $this->successResponse(
            new FaqResource($faq),
            __('FAQ retrieved successfully')
        );
    }
}
