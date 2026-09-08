<?php

namespace Modules\PPUDS\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Modules\Core\Enums\UserRole;
use Modules\Core\Traits\ApiResponse;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Http\Requests\StudentEvaluationGradeRequest;
use Modules\PPUDS\Http\Requests\StudentUniversityGradeRequest;
use Modules\PPUDS\Settings\GeneralSettings;
use Modules\PPUDS\Support\ScopesStudentCompanyVisibility;
use Modules\PPUDS\Transformers\V1\StudentGradeResource;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @OA\Tag(
 * name="Student Grades",
 * description="علامات الطلاب — عرض العلامات الثلاث ومجموعها، ووضع علامة مشرف التقييم وعلامة مشرف الجامعة"
 * )
 */
class StudentGradeController extends Controller
{
    use ApiResponse;
    use ScopesStudentCompanyVisibility;

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/student-grades",
     * summary="List student grades",
     * description="ترجع علامات الطلاب الثلاث (مشرف التقييم، مشرف الجامعة، الشركة) والمجموع. مشرف التقييم يرى الطلاب المسندين إليه وحدهم، ومشرف الجامعة يرى طلابه، والأدمن ومسؤول العلاقات المؤسسية يرون الجميع.",
     * tags={"Student Grades"},
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
     * name="filter[search]",
     * in="query",
     * required=false,
     * description="بحث بالاسم أو البريد أو الرقم الجامعي",
     *
     * @OA\Schema(type="string")
     * ),
     *
     * @OA\Parameter(
     * name="filter[student_number]",
     * in="query",
     * required=false,
     * description="الرقم الجامعي للطالب",
     *
     * @OA\Schema(type="string")
     * ),
     *
     * @OA\Parameter(
     * name="filter[evaluation_supervisor_id]",
     * in="query",
     * required=false,
     * description="تصفية بمشرف التقييم (للأدمن؛ مشرف التقييم مقيَّد بطلابه أصلاً)",
     *
     * @OA\Schema(type="integer")
     * ),
     *
     * @OA\Parameter(
     * name="filter[university_supervisor_id]",
     * in="query",
     * required=false,
     * description="تصفية بمشرف الجامعة",
     *
     * @OA\Schema(type="integer")
     * ),
     *
     * @OA\Parameter(
     * name="filter[evaluation_graded]",
     * in="query",
     * required=false,
     * description="true = رُصدت علامة مشرف التقييم، false = لم تُرصد بعد",
     *
     * @OA\Schema(type="boolean")
     * ),
     *
     * @OA\Parameter(
     * name="filter[supervisor_graded]",
     * in="query",
     * required=false,
     * description="true = رُصدت علامة مشرف الجامعة، false = لم تُرصد بعد",
     *
     * @OA\Schema(type="boolean")
     * ),
     *
     * @OA\Parameter(
     * name="filter[year]",
     * in="query",
     * required=false,
     * description="السنة الدراسية",
     *
     * @OA\Schema(type="integer", example=2026)
     * ),
     *
     * @OA\Parameter(
     * name="filter[semester]",
     * in="query",
     * required=false,
     * description="الفصل الدراسي",
     *
     * @OA\Schema(type="integer", example=1)
     * ),
     *
     * @OA\Parameter(
     * name="sort",
     * in="query",
     * required=false,
     * description="الترتيب: id, created_at, evaluation_score, supervisor_score (سابقة - للتنازلي)",
     *
     * @OA\Schema(type="string", example="-id")
     * ),
     *
     * @OA\Parameter(
     * name="per_page",
     * in="query",
     * required=false,
     * description="عدد السجلات في الصفحة",
     *
     * @OA\Schema(type="integer", default=10, example=15)
     * ),
     *
     * @OA\Response(
     * response=200,
     * description="Student grades retrieved successfully",
     *
     * @OA\JsonContent(
     * type="object",
     *
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Student grades retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="array",
     *
     * @OA\Items(ref="#/components/schemas/StudentGrade")
     * )
     * )
     * ),
     *
     * @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index()
    {
        $defaultPerPage = config('core.pagination.per_page', 10);
        $maxPerPage = config('core.pagination.max_per_page', 100);
        $perPage = min(request('per_page', $defaultPerPage), $maxPerPage);

        $grades = QueryBuilder::for($this->applyStudentGradeVisibilityScope(StudentCompany::query()))
            ->allowedFields(StudentGradeResource::allowedFields())
            ->allowedFilters(StudentGradeResource::allowedFilters())
            ->allowedSorts(StudentGradeResource::allowedSorts())
            ->allowedIncludes(StudentGradeResource::allowedIncludes())
            ->with([
                'student.studentProfile.major',
                'evaluationSupervisor',
                'registration.supervisor',
                'company',
                'branch',
                'department',
            ])
            ->paginate($perPage)
            ->appends(request()->query());

        return $this->successResponse(
            StudentGradeResource::collection($grades),
            __('Student grades retrieved successfully')
        );
    }

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/student-grades/evaluation-students",
     * summary="List the students assigned to the authenticated evaluation supervisor",
     * description="ترجع الطلاب المسندين لمشرف التقييم الحالي مع علامته وحالة الرصد، إضافةً إلى ملخص (المجموع، المرصود، المتبقي). الأدمن يستطيع تمرير evaluation_supervisor_id لعرض طلاب مشرف معيّن.",
     * tags={"Student Grades"},
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
     * name="status",
     * in="query",
     * required=false,
     * description="تصفية حسب حالة الرصد: pending (لم تُرصد)، graded (مرصودة)، all",
     *
     * @OA\Schema(type="string", enum={"pending", "graded", "all"}, default="all")
     * ),
     *
     * @OA\Parameter(
     * name="evaluation_supervisor_id",
     * in="query",
     * required=false,
     * description="للأدمن فقط: عرض طلاب مشرف تقييم محدد",
     *
     * @OA\Schema(type="integer", example=5)
     * ),
     *
     * @OA\Response(
     * response=200,
     * description="Evaluation supervisor students retrieved successfully",
     *
     * @OA\JsonContent(
     * type="object",
     *
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Evaluation supervisor students retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="object",
     *
     * @OA\Property(property="evaluation_supervisor_id", type="integer", example=5),
     * @OA\Property(property="status", type="string", example="all"),
     * @OA\Property(property="total_students", type="integer", example=12),
     * @OA\Property(property="graded_students", type="integer", example=7),
     * @OA\Property(property="pending_students", type="integer", example=5),
     * @OA\Property(property="max_evaluation_score", type="integer", example=25),
     * @OA\Property(
     * property="students",
     * type="array",
     *
     * @OA\Items(ref="#/components/schemas/StudentGrade")
     * )
     * )
     * )
     * ),
     *
     * @OA\Response(response=403, description="You are not authorized to perform this action")
     * )
     */
    public function evaluationStudents()
    {
        $user = auth()->user();

        $supervisorId = $this->currentUserIsAdmin() && filled(request('evaluation_supervisor_id'))
            ? (int) request('evaluation_supervisor_id')
            : (int) $user->id;

        // غير الأدمن لا يرى إلا طلابه هو، حتى لو مرّر معرّفاً آخر.
        if (! $this->currentUserIsAdmin() && ! $user->hasRole(UserRole::EVALUATION_SUPERVISOR->value)) {
            return $this->errorResponse(__('You are not authorized to perform this action'), 403);
        }

        $status = request('status', 'all');

        $baseQuery = fn (): Builder => StudentCompany::query()
            ->where('evaluation_supervisor_id', $supervisorId);

        $totalStudents = $baseQuery()->count();
        $gradedStudents = $baseQuery()->whereNotNull('evaluation_score')->count();
        $pendingStudents = max($totalStudents - $gradedStudents, 0);

        $students = match ($status) {
            'pending' => $baseQuery()->whereNull('evaluation_score'),
            'graded' => $baseQuery()->whereNotNull('evaluation_score'),
            default => $baseQuery(),
        };

        $students = $students
            ->with([
                'student.studentProfile.major',
                'evaluationSupervisor',
                'registration.supervisor',
                'company',
                'branch',
                'department',
            ])
            ->orderBy('id')
            ->get();

        return $this->successResponse([
            'evaluation_supervisor_id' => $supervisorId,
            'status' => $status,
            'total_students' => $totalStudents,
            'graded_students' => $gradedStudents,
            'pending_students' => $pendingStudents,
            'max_evaluation_score' => app(GeneralSettings::class)->evaluation_supervisor_max_grade,
            'students' => StudentGradeResource::collection($students)->resolve(request()),
        ], __('Evaluation supervisor students retrieved successfully'));
    }

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/student-grades/{studentCompany}",
     * summary="Get a single student's grades",
     * description="ترجع علامات طالب واحد (مشرف التقييم، مشرف الجامعة، الشركة) والمجموع.",
     * tags={"Student Grades"},
     * security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     * name="studentCompany",
     * in="path",
     * required=true,
     * description="Student Company ID",
     *
     * @OA\Schema(type="integer", example=1)
     * ),
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
     * @OA\Response(
     * response=200,
     * description="Student grade retrieved successfully",
     *
     * @OA\JsonContent(
     * type="object",
     *
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Student grade retrieved successfully"),
     * @OA\Property(property="data", ref="#/components/schemas/StudentGrade")
     * )
     * ),
     *
     * @OA\Response(response=404, description="Student grade not found")
     * )
     */
    public function show(StudentCompany $studentCompany)
    {
        $studentCompany = $this->applyStudentGradeVisibilityScope(StudentCompany::query())
            ->whereKey($studentCompany->id)
            ->with([
                'student.studentProfile.major',
                'evaluationSupervisor',
                'registration.supervisor',
                'company',
                'branch',
                'department',
            ])
            ->firstOrFail();

        return $this->successResponse(
            new StudentGradeResource($studentCompany),
            __('Student grade retrieved successfully')
        );
    }

    /**
     * @OA\Patch(
     * path="/api/v1/ppuds/student-grades/{studentCompany}/evaluation",
     * summary="Set the evaluation supervisor grade",
     * description="يضع علامة مشرف التقييم للطالب. الصلاحية المطلوبة EvaluationSupervisorStudent Grade، ولا يُسمح إلا لمشرف التقييم المسند لهذا الطالب أو للأدمن. الحد الأقصى مأخوذ من الإعدادات.",
     * tags={"Student Grades"},
     * security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     * name="studentCompany",
     * in="path",
     * required=true,
     * description="Student Company ID",
     *
     * @OA\Schema(type="integer", example=1)
     * ),
     *
     * @OA\RequestBody(
     * required=true,
     *
     * @OA\JsonContent(
     * required={"evaluation_score"},
     *
     * @OA\Property(property="evaluation_score", type="integer", minimum=0, example=20, description="العلامة، بحد أقصى القيمة المحددة في الإعدادات (افتراضياً 25)")
     * )
     * ),
     *
     * @OA\Response(
     * response=200,
     * description="Grade saved successfully",
     *
     * @OA\JsonContent(
     * type="object",
     *
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Grade saved successfully"),
     * @OA\Property(property="data", ref="#/components/schemas/StudentGrade")
     * )
     * ),
     *
     * @OA\Response(response=403, description="You are not authorized to perform this action"),
     * @OA\Response(response=404, description="Student grade not found"),
     * @OA\Response(response=422, description="Validation error")
     * )
     */
    public function updateEvaluationGrade(StudentEvaluationGradeRequest $request, StudentCompany $studentCompany)
    {
        if ($response = $this->denyUnlessCan('EvaluationSupervisorStudent Grade')) {
            return $response;
        }

        if ($response = $this->denyUnlessAssignedEvaluationSupervisor($studentCompany)) {
            return $response;
        }

        $studentCompany->update([
            'evaluation_score' => (int) $request->validated('evaluation_score'),
        ]);

        return $this->respondWithGrade($studentCompany);
    }

    /**
     * @OA\Patch(
     * path="/api/v1/ppuds/student-grades/{studentCompany}/supervisor",
     * summary="Set the university supervisor grade",
     * description="يضع علامة مشرف الجامعة للطالب. الصلاحية المطلوبة PracticalSupervisorStudent Grade، ولا يُسمح إلا لمشرف الجامعة المسؤول عن تسجيل الطالب أو للأدمن. الحد الأقصى مأخوذ من الإعدادات.",
     * tags={"Student Grades"},
     * security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     * name="studentCompany",
     * in="path",
     * required=true,
     * description="Student Company ID",
     *
     * @OA\Schema(type="integer", example=1)
     * ),
     *
     * @OA\RequestBody(
     * required=true,
     *
     * @OA\JsonContent(
     * required={"supervisor_score"},
     *
     * @OA\Property(property="supervisor_score", type="integer", minimum=0, example=30, description="العلامة، بحد أقصى القيمة المحددة في الإعدادات (افتراضياً 35)")
     * )
     * ),
     *
     * @OA\Response(
     * response=200,
     * description="Grade saved successfully",
     *
     * @OA\JsonContent(
     * type="object",
     *
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Grade saved successfully"),
     * @OA\Property(property="data", ref="#/components/schemas/StudentGrade")
     * )
     * ),
     *
     * @OA\Response(response=403, description="You are not authorized to perform this action"),
     * @OA\Response(response=404, description="Student grade not found"),
     * @OA\Response(response=422, description="Validation error")
     * )
     */
    public function updateSupervisorGrade(StudentUniversityGradeRequest $request, StudentCompany $studentCompany)
    {
        if ($response = $this->denyUnlessCan('PracticalSupervisorStudent Grade')) {
            return $response;
        }

        if ($response = $this->denyUnlessAssignedUniversitySupervisor($studentCompany)) {
            return $response;
        }

        $studentCompany->update([
            'supervisor_score' => (int) $request->validated('supervisor_score'),
        ]);

        return $this->respondWithGrade($studentCompany);
    }

    /**
     * مشرف التقييم لا تشمله ScopesStudentCompanyVisibility، فلو تُرك لها لعاد
     * استعلامه بلا قيد ورأى كل الطلاب. لذلك نقيّده هنا صراحةً بالطلاب المسندين
     * إليه، مع بقاء قيد مشرف الجامعة كما هو.
     */
    protected function applyStudentGradeVisibilityScope(Builder $query): Builder
    {
        $user = auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->currentUserIsAdmin()) {
            return $query;
        }

        $isEvaluationSupervisor = $user->hasRole(UserRole::EVALUATION_SUPERVISOR->value);
        $isUniversitySupervisor = $this->shouldScopeUniversitySupervisorStudentCompanies();

        if ($isEvaluationSupervisor || $isUniversitySupervisor) {
            return $query->where(function (Builder $scoped) use ($user, $isEvaluationSupervisor, $isUniversitySupervisor) {
                if ($isEvaluationSupervisor) {
                    $scoped->orWhere('evaluation_supervisor_id', $user->id);
                }

                if ($isUniversitySupervisor) {
                    $scoped->orWhereHas(
                        'registration',
                        fn (Builder $registrationQuery): Builder => $registrationQuery->where('supervisor_id', $user->id)
                    );
                }
            });
        }

        return $this->applyStudentCompanyVisibilityScope($query);
    }

    private function respondWithGrade(StudentCompany $studentCompany): JsonResponse
    {
        $studentCompany->load([
            'student.studentProfile.major',
            'evaluationSupervisor',
            'registration.supervisor',
            'company',
            'branch',
            'department',
        ]);

        return $this->successResponse(
            new StudentGradeResource($studentCompany),
            __('Grade saved successfully')
        );
    }

    /**
     * صلاحيات المشروع مسجَّلة على حارس web، بينما تعمل طلبات الـ API على حارس
     * api، فنتحقق من حارس web صراحةً وإلا رُفض المشرف صاحب الصلاحية.
     */
    private function denyUnlessCan(string $permission): ?JsonResponse
    {
        if (auth()->user()?->checkPermissionTo($permission, 'web')) {
            return null;
        }

        return $this->errorResponse(__('You are not authorized to perform this action'), 403);
    }

    private function denyUnlessAssignedEvaluationSupervisor(StudentCompany $studentCompany): ?JsonResponse
    {
        if ($this->currentUserIsAdmin()) {
            return null;
        }

        if ((int) $studentCompany->evaluation_supervisor_id === (int) auth()->id()) {
            return null;
        }

        return $this->errorResponse(__('You are not assigned as the evaluation supervisor for this student.'), 403);
    }

    private function denyUnlessAssignedUniversitySupervisor(StudentCompany $studentCompany): ?JsonResponse
    {
        if ($this->currentUserIsAdmin()) {
            return null;
        }

        $studentCompany->loadMissing('registration');

        if ((int) $studentCompany->registration?->supervisor_id === (int) auth()->id()) {
            return null;
        }

        return $this->errorResponse(__('You are not assigned as the university supervisor for this student.'), 403);
    }
}
