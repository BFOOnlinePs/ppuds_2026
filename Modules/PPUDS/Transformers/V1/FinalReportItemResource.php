<?php

namespace Modules\PPUDS\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 * schema="FinalReportItemResource",
 * title="Final Report Item Resource",
 * description="بند من أهم المساهمات أو من صعوبات التدريب",
 * @OA\Xml(name="FinalReportItemResource"),
 * @OA\Property(property="id", type="integer", example=1),
 * @OA\Property(property="content", type="string", example="إعداد دليل استخدام النظام"),
 * @OA\Property(property="sort_order", type="integer", example=0)
 * )
 */
class FinalReportItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'content'    => $this->content,
            'sort_order' => $this->sort_order,
        ];
    }
}
