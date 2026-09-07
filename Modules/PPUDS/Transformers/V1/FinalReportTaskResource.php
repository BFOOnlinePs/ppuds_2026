<?php

namespace Modules\PPUDS\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 * schema="FinalReportTaskResource",
 * title="Final Report Task Resource",
 * description="صف واحد من جدول المهام التدريبية",
 * @OA\Xml(name="FinalReportTaskResource"),
 * @OA\Property(property="id", type="integer", example=1),
 * @OA\Property(property="task_name", type="string", example="تطوير واجهات المستخدم"),
 * @OA\Property(property="task_details", type="string", nullable=true, example="بناء صفحات لوحة التحكم"),
 * @OA\Property(property="work_duration", type="string", nullable=true, example="10 أيام - 60 ساعة"),
 * @OA\Property(property="notes", type="string", nullable=true, example="بإشراف مهندس الفريق"),
 * @OA\Property(property="sort_order", type="integer", example=0)
 * )
 */
class FinalReportTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'task_name'     => $this->task_name,
            'task_details'  => $this->task_details,
            'work_duration' => $this->work_duration,
            'notes'         => $this->notes,
            'sort_order'    => $this->sort_order,
        ];
    }
}
