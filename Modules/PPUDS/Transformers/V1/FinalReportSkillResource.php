<?php

namespace Modules\PPUDS\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 * schema="FinalReportSkillResource",
 * title="Final Report Skill Resource",
 * description="صف واحد من جدول المهارات المكتسبة من التدريب",
 * @OA\Xml(name="FinalReportSkillResource"),
 * @OA\Property(property="id", type="integer", example=1),
 * @OA\Property(property="skill", type="string", example="العمل ضمن فريق"),
 * @OA\Property(property="mastery_percentage", type="integer", nullable=true, example=80),
 * @OA\Property(property="notes", type="string", nullable=true, example="تحسّن ملحوظ في الشهر الأخير"),
 * @OA\Property(property="sort_order", type="integer", example=0)
 * )
 */
class FinalReportSkillResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'skill'              => $this->skill,
            'mastery_percentage' => $this->mastery_percentage,
            'notes'              => $this->notes,
            'sort_order'         => $this->sort_order,
        ];
    }
}
