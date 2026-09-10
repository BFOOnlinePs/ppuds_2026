<?php

namespace Modules\PPUDS\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Transformers\V1\UserResource;
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Enums\FinalReportItemType;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

/**
 * @OA\Schema(
 * schema="FinalReportResource",
 * title="Final Report Resource",
 * description="التقرير النهائي للتدريب الميداني",
 * @OA\Xml(name="FinalReportResource"),
 * @OA\Property(property="id", type="integer", example=1),
 * @OA\Property(property="registration_id", type="integer", example=12),
 * @OA\Property(property="student_id", type="integer", example=44),
 * @OA\Property(property="role_description", type="string", nullable=true, example="<p>عملت في قسم التطوير...</p>"),
 * @OA\Property(property="summary", type="string", nullable=true, example="<p>استفدت من التدريب في...</p>"),
 * @OA\Property(property="status", type="integer", enum={1, 2}, description="1 = مسودة، 2 = تم التسليم", example=1),
 * @OA\Property(property="status_label", type="string", example="مسودة"),
 * @OA\Property(property="is_editable", type="boolean", example=true),
 * @OA\Property(property="submitted_at", type="string", format="date-time", nullable=true),
 * @OA\Property(property="final_file", type="string", nullable=true, description="رابط المرفق الاختياري، و null إذا لم يرفع الطالب ملفاً", example="https://example.com/storage/ppuds/registers/report.pdf"),
 * @OA\Property(property="final_presentation", type="string", nullable=true, description="رابط العرض التقديمي الإجباري، و null إذا لم يُرفع بعد أو لم تُحمَّل علاقة التسجيل", example="https://example.com/storage/ppuds/registers/20260910_ab12cd34_deck.pptx"),
 * @OA\Property(property="final_code", type="string", nullable=true, description="رابط ملف بايثون الاختياري، و null إذا لم يرفعه الطالب", example="https://example.com/storage/ppuds/registers/20260910_ef56gh78_main.py"),
 * @OA\Property(property="tasks", type="array", @OA\Items(ref="#/components/schemas/FinalReportTaskResource")),
 * @OA\Property(property="skills", type="array", @OA\Items(ref="#/components/schemas/FinalReportSkillResource")),
 * @OA\Property(property="contributions", type="array", @OA\Items(ref="#/components/schemas/FinalReportItemResource")),
 * @OA\Property(property="difficulties", type="array", @OA\Items(ref="#/components/schemas/FinalReportItemResource")),
 * @OA\Property(property="created_at", type="string", format="date-time")
 * )
 */
class FinalReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'registration_id'  => $this->registration_id,
            'student_id'       => $this->student_id,
            'role_description' => $this->role_description,
            'summary'          => $this->summary,
            'status'           => $this->status?->value,
            'status_label'     => $this->status?->getLabel(),
            'is_editable'      => $this->isEditable(),
            'submitted_at'     => $this->submitted_at,
            'final_file'       => $this->attachmentUrl(),
            'final_presentation' => $this->mediaUrl(Registration::PRESENTATION_COLLECTION),
            'final_code'       => $this->mediaUrl(Registration::CODE_COLLECTION),
            'tasks'            => FinalReportTaskResource::collection($this->whenLoaded('tasks')),
            'skills'           => FinalReportSkillResource::collection($this->whenLoaded('skills')),
            'contributions'    => FinalReportItemResource::collection(
                $this->itemsOfType(FinalReportItemType::CONTRIBUTION)
            ),
            'difficulties'     => FinalReportItemResource::collection(
                $this->itemsOfType(FinalReportItemType::DIFFICULTY)
            ),
            'created_at'       => $this->created_at,

            'student'          => UserResource::make($this->whenLoaded('student')),
        ];
    }

    /**
     * المرفق الاختياري محفوظ في مجموعة final_file على التسجيل.
     */
    protected function attachmentUrl(): ?string
    {
        return $this->mediaUrl('final_file');
    }

    /**
     * ملفات التقرير محفوظة على التسجيل، فترجع null ما لم تكن العلاقة محمَّلة
     * حتى لا يُطلَق استعلام إضافي لكل عنصر في القوائم.
     */
    protected function mediaUrl(string $collection): ?string
    {
        if (! $this->resource->relationLoaded('registration')) {
            return null;
        }

        return $this->registration?->hasMedia($collection)
            ? $this->registration->getFirstMediaUrl($collection)
            : null;
    }

    /**
     * المساهمات والصعوبات محفوظة في نفس الجدول، فنقسمها هنا حسب النوع.
     */
    protected function itemsOfType(FinalReportItemType $type)
    {
        if (! $this->resource->relationLoaded('items')) {
            return collect();
        }

        return $this->items->where('type', $type)->values();
    }

    public static function allowedFields(): array
    {
        return [
            'id',
            'registration_id',
            'student_id',
            'role_description',
            'summary',
            'status',
            'submitted_at',
            'created_at',
        ];
    }

    public static function allowedFilters(): array
    {
        return [
            AllowedFilter::exact('id'),
            AllowedFilter::exact('student_id'),
            AllowedFilter::exact('registration_id'),
            AllowedFilter::exact('status'),
        ];
    }

    public static function allowedSorts(): array
    {
        return [
            AllowedSort::field('id'),
            AllowedSort::field('submitted_at'),
            AllowedSort::field('created_at'),
        ];
    }

    public static function allowedIncludes(): array
    {
        return [
            'tasks',
            'skills',
            'items',
            'student',
            'registration',
            'createdBy',
        ];
    }
}
