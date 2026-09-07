<?php

namespace Modules\PPUDS\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Core\Traits\ApiResponse;
use Modules\PPUDS\Entities\FinalReport;
use Modules\PPUDS\Http\Controllers\Api\V1\Concerns\EnsuresCurrentRegistration;
use Modules\PPUDS\Http\Requests\FinalReportRequest;
use Modules\PPUDS\Services\FinalReportService;
use Modules\PPUDS\Transformers\V1\FinalReportResource;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @OA\Tag(
 * name="Final Reports",
 * description="تسليم التقرير النهائي للتدريب الميداني — متاح للطالب فقط"
 * )
 */
class FinalReportController extends Controller
{
    use ApiResponse;
    use EnsuresCurrentRegistration;

    public function __construct(protected FinalReportService $finalReports) {}

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/final-reports",
     * summary="List the authenticated student's final reports",
     * description="ترجع تقارير الطالب الحالي فقط. الصلاحية للطالب فقط.",
     * tags={"Final Reports"},
     * security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     * name="Accept-Language",
     * in="header",
     * required=false,
     * description="Language header (ar or en)",
     *
     * @OA\Schema(type="string", default="ar", example="ar")
     * ),
     *
     * @OA\Parameter(
     * name="filter[status]",
     * in="query",
     * required=false,
     * description="1 = draft, 2 = submitted",
     *
     * @OA\Schema(type="integer")
     * ),
     *
     * @OA\Response(
     * response=200,
     * description="Final reports retrieved successfully",
     *
     * @OA\JsonContent(
     * type="object",
     *
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Final reports retrieved successfully"),
     * @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/FinalReportResource"))
     * )
     * ),
     * @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function index()
    {
        if ($denied = $this->denyUnlessStudentCan('FinalReport View List')) {
            return $denied;
        }

        $defaultPerPage = config('core.pagination.per_page', 10);
        $maxPerPage = config('core.pagination.max_per_page', 100);
        $perPage = min(request('per_page', $defaultPerPage), $maxPerPage);

        $finalReports = QueryBuilder::for(FinalReport::class)
            ->where('student_id', auth()->id())
            ->with(['tasks', 'skills', 'items'])
            ->allowedFields(FinalReportResource::allowedFields())
            ->allowedFilters(FinalReportResource::allowedFilters())
            ->allowedSorts(FinalReportResource::allowedSorts())
            ->allowedIncludes(FinalReportResource::allowedIncludes())
            ->paginate($perPage)
            ->appends(request()->query());

        return $this->successResponse(
            FinalReportResource::collection($finalReports),
            __('Final reports retrieved successfully')
        );
    }

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/final-reports/current",
     * summary="Get the final report of the current semester",
     * description="ترجع تقرير الطالب للفصل الحالي، و data = null إذا لم يبدأ بتعبئته بعد.",
     * tags={"Final Reports"},
     * security={{"sanctum": {}}},
     *
     * @OA\Response(
     * response=200,
     * description="Final report retrieved successfully",
     *
     * @OA\JsonContent(
     * type="object",
     *
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Final report retrieved successfully"),
     * @OA\Property(property="data", ref="#/components/schemas/FinalReportResource")
     * )
     * ),
     * @OA\Response(response=403, description="Forbidden"),
     * @OA\Response(response=422, description="No registration in the current semester")
     * )
     */
    public function current()
    {
        if ($denied = $this->denyUnlessStudentCan('FinalReport View')) {
            return $denied;
        }

        $registration = $this->finalReports->currentRegistrationFor(auth()->id());

        if ($error = $this->ensureRegistrationInCurrentSemester($registration)) {
            return $error;
        }

        $report = $this->finalReports->reportForRegistration($registration);

        return $this->successResponse(
            $report ? new FinalReportResource($report) : null,
            __('Final report retrieved successfully')
        );
    }

    /**
     * @OA\Post(
     * path="/api/v1/ppuds/final-reports",
     * summary="Create or update the current draft final report",
     * description="يحفظ التقرير كمسودة على تسجيل الطالب في الفصل الحالي. إرسال الطلب مرة أخرى يستبدل صفوف الجداول بالكامل. لا يمكن التعديل بعد التسليم.",
     * tags={"Final Reports"},
     * security={{"sanctum": {}}},
     *
     * @OA\RequestBody(
     * required=true,
     *
     * @OA\MediaType(
     * mediaType="application/json",
     *
     * @OA\Schema(
     * @OA\Property(property="role_description", type="string", description="طبيعة دور الطالب والقسم الذي عمل فيه (نص منسّق)", example="عملت في قسم تطوير البرمجيات"),
     * @OA\Property(property="summary", type="string", description="ملخص التدريب الميداني (نص منسّق)", example="استفدت من التدريب في"),
     * @OA\Property(
     * property="tasks",
     * type="array",
     * description="جدول المهام التدريبية",
     * @OA\Items(
     * type="object",
     * required={"task_name"},
     * @OA\Property(property="task_name", type="string", example="تطوير واجهات المستخدم"),
     * @OA\Property(property="task_details", type="string", example="بناء صفحات لوحة التحكم"),
     * @OA\Property(property="work_duration", type="string", example="10 أيام - 60 ساعة"),
     * @OA\Property(property="notes", type="string", example="بإشراف مهندس الفريق")
     * )
     * ),
     * @OA\Property(
     * property="skills",
     * type="array",
     * description="جدول المهارات المكتسبة من التدريب",
     * @OA\Items(
     * type="object",
     * required={"skill"},
     * @OA\Property(property="skill", type="string", example="العمل ضمن فريق"),
     * @OA\Property(property="mastery_percentage", type="integer", minimum=0, maximum=100, example=80),
     * @OA\Property(property="notes", type="string", example="تحسّن ملحوظ في الشهر الأخير")
     * )
     * ),
     * @OA\Property(
     * property="contributions",
     * type="array",
     * description="أهم المساهمات",
     * @OA\Items(
     * type="object",
     * required={"content"},
     * @OA\Property(property="content", type="string", example="إعداد دليل استخدام النظام")
     * )
     * ),
     * @OA\Property(
     * property="difficulties",
     * type="array",
     * description="صعوبات التدريب",
     * @OA\Items(
     * type="object",
     * required={"content"},
     * @OA\Property(property="content", type="string", example="ضيق الوقت مع تزامن الامتحانات")
     * )
     * )
     * )
     * ),
     *
     * @OA\MediaType(
     * mediaType="multipart/form-data",
     *
     * @OA\Schema(
     * @OA\Property(property="role_description", type="string"),
     * @OA\Property(property="summary", type="string"),
     * @OA\Property(property="final_file", type="string", format="binary", description="مرفق اختياري: jpeg, png, jpg أو pdf بحد أقصى 2 ميجابايت. رفع ملف جديد يستبدل السابق. عند استخدام multipart تُرسل الجداول بصيغة الأقواس مثل tasks[0][task_name].")
     * )
     * )
     * ),
     *
     * @OA\Response(
     * response=200,
     * description="Final report saved successfully",
     *
     * @OA\JsonContent(
     * type="object",
     *
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Final report saved successfully"),
     * @OA\Property(property="data", ref="#/components/schemas/FinalReportResource")
     * )
     * ),
     * @OA\Response(response=403, description="Forbidden"),
     * @OA\Response(response=422, description="Validation error, the report is already submitted, or submission is closed in settings")
     * )
     */
    public function store(FinalReportRequest $request)
    {
        if ($denied = $this->denyUnlessStudentCan('FinalReport Create')) {
            return $denied;
        }

        if ($closed = $this->denyWhenSubmissionClosed()) {
            return $closed;
        }

        $registration = $this->finalReports->currentRegistrationFor(auth()->id());

        if ($error = $this->ensureRegistrationInCurrentSemester($registration)) {
            return $error;
        }

        $existing = $this->finalReports->reportForRegistration($registration);

        if ($existing && ! $existing->isEditable()) {
            return $this->errorResponse(__('The final report has already been submitted and can no longer be edited.'), 422);
        }

        if (! $this->finalReports->saveAttachment($registration, $request->file('final_file'))) {
            return $this->errorResponse(__('Failed to upload the final report file. Please try again.'), 500);
        }

        $report = $this->finalReports->save($registration, $request->validated(), auth()->user());

        return $this->successResponse(
            new FinalReportResource($report),
            __('Final report saved successfully')
        );
    }

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/final-reports/{id}",
     * summary="Get a final report by id",
     * tags={"Final Reports"},
     * security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     * name="id",
     * in="path",
     * required=true,
     * description="Final Report ID",
     *
     * @OA\Schema(type="integer")
     * ),
     *
     * @OA\Response(
     * response=200,
     * description="Final report retrieved successfully",
     *
     * @OA\JsonContent(
     * type="object",
     *
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Final report retrieved successfully"),
     * @OA\Property(property="data", ref="#/components/schemas/FinalReportResource")
     * )
     * ),
     * @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function show(FinalReport $finalReport)
    {
        if ($denied = $this->denyUnlessStudentCan('FinalReport View')) {
            return $denied;
        }

        if ($denied = $this->denyUnlessOwned($finalReport)) {
            return $denied;
        }

        return $this->successResponse(
            new FinalReportResource($finalReport->load(['tasks', 'skills', 'items'])),
            __('Final report retrieved successfully')
        );
    }

    /**
     * @OA\Patch(
     * path="/api/v1/ppuds/final-reports/{id}",
     * summary="Update a draft final report",
     * description="نفس حقول الإنشاء. تعمل فقط ما دام التقرير مسودة.",
     * tags={"Final Reports"},
     * security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     * name="id",
     * in="path",
     * required=true,
     * description="Final Report ID",
     *
     * @OA\Schema(type="integer")
     * ),
     *
     * @OA\RequestBody(
     * required=true,
     *
     * @OA\MediaType(
     * mediaType="application/json",
     *
     * @OA\Schema(
     * @OA\Property(property="role_description", type="string", example="عملت في قسم تطوير البرمجيات"),
     * @OA\Property(property="summary", type="string", example="استفدت من التدريب في"),
     * @OA\Property(property="tasks", type="array", @OA\Items(ref="#/components/schemas/FinalReportTaskResource")),
     * @OA\Property(property="skills", type="array", @OA\Items(ref="#/components/schemas/FinalReportSkillResource")),
     * @OA\Property(property="contributions", type="array", @OA\Items(ref="#/components/schemas/FinalReportItemResource")),
     * @OA\Property(property="difficulties", type="array", @OA\Items(ref="#/components/schemas/FinalReportItemResource"))
     * )
     * ),
     *
     * @OA\MediaType(
     * mediaType="multipart/form-data",
     *
     * @OA\Schema(
     * @OA\Property(property="_method", type="string", example="PATCH"),
     * @OA\Property(property="role_description", type="string"),
     * @OA\Property(property="summary", type="string"),
     * @OA\Property(property="final_file", type="string", format="binary", description="مرفق اختياري: jpeg, png, jpg أو pdf بحد أقصى 2 ميجابايت. رفع ملف جديد يستبدل السابق. عند استخدام multipart تُرسل الجداول بصيغة الأقواس مثل tasks[0][task_name].")
     * )
     * )
     * ),
     *
     * @OA\Response(
     * response=200,
     * description="Final report saved successfully",
     *
     * @OA\JsonContent(
     * type="object",
     *
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Final report saved successfully"),
     * @OA\Property(property="data", ref="#/components/schemas/FinalReportResource")
     * )
     * ),
     * @OA\Response(response=403, description="Forbidden"),
     * @OA\Response(response=422, description="Validation error, the report is already submitted, or submission is closed in settings")
     * )
     */
    public function update(FinalReportRequest $request, FinalReport $finalReport)
    {
        if ($denied = $this->denyUnlessStudentCan('FinalReport Update')) {
            return $denied;
        }

        if ($closed = $this->denyWhenSubmissionClosed()) {
            return $closed;
        }

        if ($denied = $this->denyUnlessOwned($finalReport)) {
            return $denied;
        }

        if (! $finalReport->isEditable()) {
            return $this->errorResponse(__('The final report has already been submitted and can no longer be edited.'), 422);
        }

        $finalReport->loadMissing('registration');

        if ($error = $this->ensureRegistrationInCurrentSemester($finalReport->registration)) {
            return $error;
        }

        if (! $this->finalReports->saveAttachment($finalReport->registration, $request->file('final_file'))) {
            return $this->errorResponse(__('Failed to upload the final report file. Please try again.'), 500);
        }

        $report = $this->finalReports->save($finalReport->registration, $request->validated(), auth()->user());

        return $this->successResponse(
            new FinalReportResource($report),
            __('Final report saved successfully')
        );
    }

    /**
     * @OA\Post(
     * path="/api/v1/ppuds/final-reports/{id}/submit",
     * summary="Submit the final report",
     * description="التسليم النهائي — بعده يصبح التقرير غير قابل للتعديل.",
     * tags={"Final Reports"},
     * security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     * name="id",
     * in="path",
     * required=true,
     * description="Final Report ID",
     *
     * @OA\Schema(type="integer")
     * ),
     *
     * @OA\Response(
     * response=200,
     * description="Final report submitted successfully",
     *
     * @OA\JsonContent(
     * type="object",
     *
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Final report submitted successfully"),
     * @OA\Property(property="data", ref="#/components/schemas/FinalReportResource")
     * )
     * ),
     * @OA\Response(response=403, description="Forbidden"),
     * @OA\Response(response=422, description="Already submitted, still incomplete, or submission is closed in settings")
     * )
     */
    public function submit(FinalReport $finalReport)
    {
        if ($denied = $this->denyUnlessStudentCan('FinalReport Submit')) {
            return $denied;
        }

        if ($closed = $this->denyWhenSubmissionClosed()) {
            return $closed;
        }

        if ($denied = $this->denyUnlessOwned($finalReport)) {
            return $denied;
        }

        if (! $finalReport->isEditable()) {
            return $this->errorResponse(__('The final report has already been submitted and can no longer be edited.'), 422);
        }

        $finalReport->loadMissing(['registration', 'tasks', 'skills', 'items']);

        if ($error = $this->ensureRegistrationInCurrentSemester($finalReport->registration)) {
            return $error;
        }

        if ($finalReport->tasks->isEmpty() || blank($finalReport->summary)) {
            return $this->errorResponse(
                __('Please add at least one training task and write the summary before submitting.'),
                422
            );
        }

        return $this->successResponse(
            new FinalReportResource($this->finalReports->submit($finalReport)),
            __('Final report submitted successfully')
        );
    }

    /**
     * الشاشة والـ API مقصورتان على الطالب، والصلاحيات ممنوحة لدور الطالب وحده.
     * صلاحيات المشروع مسجَّلة على حارس web، بينما تعمل طلبات الـ API على حارس api،
     * فنتحقق من حارس web صراحةً وإلا رُفض الطالب صاحب الصلاحية.
     */
    private function denyUnlessStudentCan(string $permission): ?JsonResponse
    {
        if (auth()->user()?->checkPermissionTo($permission, 'web')) {
            return null;
        }

        return $this->errorResponse(__('You are not authorized to perform this action'), 403);
    }

    /**
     * القراءة تبقى متاحة بعد الإغلاق ليرى الطالب ما سلّمه، أما الكتابة فتُمنع.
     */
    private function denyWhenSubmissionClosed(): ?JsonResponse
    {
        if ($this->finalReports->submissionIsOpen()) {
            return null;
        }

        return $this->errorResponse(__('Final report submission is currently closed.'), 422);
    }

    private function denyUnlessOwned(FinalReport $finalReport): ?JsonResponse
    {
        if ((int) $finalReport->student_id === (int) auth()->id()) {
            return null;
        }

        return $this->errorResponse(__('You are not authorized to perform this action'), 403);
    }
}
