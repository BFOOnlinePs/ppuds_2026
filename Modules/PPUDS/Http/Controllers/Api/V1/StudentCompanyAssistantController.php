<?php

namespace Modules\PPUDS\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Modules\Core\Actions\StudentCompanyAssistant\FindCompaniesForCompanyAssistant;
use Modules\Core\Actions\StudentCompanyAssistant\FindStudentsForCompanyAssistant;
use Modules\Core\Actions\StudentCompanyAssistant\LinkSuggestedCompanyToStudent;
use Modules\Core\Actions\StudentCompanyAssistant\ResolveStudentCompanyRegistration;
use Modules\Core\Entities\User;
use Modules\Core\Services\StudentCompanySuggestionService;
use Modules\Core\Traits\ApiResponse;
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Http\Controllers\Api\V1\Concerns\EnsuresCurrentRegistration;
use Modules\PPUDS\Http\Requests\StudentCompanyAssistant\LinkAllAssistantCompaniesRequest;
use Modules\PPUDS\Http\Requests\StudentCompanyAssistant\LinkAssistantCompanyRequest;
use Modules\PPUDS\Http\Requests\StudentCompanyAssistant\SearchAssistantCompaniesRequest;
use Modules\PPUDS\Http\Requests\StudentCompanyAssistant\SearchAssistantStudentsRequest;
use Modules\PPUDS\Http\Requests\StudentCompanyAssistant\SuggestAssistantCompaniesRequest;
use Modules\PPUDS\Transformers\V1\RegistrationResource;
use Modules\PPUDS\Transformers\V1\StudentCompanyAssistant\StudentCompanyAssistantCompanyResource;
use Modules\PPUDS\Transformers\V1\StudentCompanyAssistant\StudentCompanyAssistantStudentResource;
use Modules\PPUDS\Transformers\V1\StudentCompanyAssistant\StudentCompanyAssistantSuggestionResource;
use Modules\PPUDS\Transformers\V1\StudentCompanyResource;

/**
 * @OA\Tag(
 * name="Student Company Assistant",
 * description="مساعد ربط الطلاب بالشركات: بحث عن طالب، اقتراح شركات مناسبة له، ثم ربطها بتسجيله في الفصل الحالي"
 * )
 */
class StudentCompanyAssistantController extends Controller
{
    use ApiResponse;
    use EnsuresCurrentRegistration;

    /**
     * @OA\Post(
     * path="/api/v1/ppuds/student-company-assistant/students/search",
     * summary="Search students by name or number",
     * description="الخطوة الأولى في المساعد. يبحث بالاسم أو الرقم الجامعي ويعيد قائمة مرشحين. إذا تطابق البحث تماماً مع طالب واحد يعود معرّفه في exact_match_id ليختاره التطبيق تلقائياً.",
     * tags={"Student Company Assistant"},
     * security={{"sanctum": {}}},
     *
     * @OA\RequestBody(
     * required=true,
     *
     * @OA\JsonContent(
     * required={"query"},
     *
     * @OA\Property(property="query", type="string", minLength=2, maxLength=120, example="محمد", description="اسم الطالب أو رقمه الجامعي"),
     * @OA\Property(property="limit", type="integer", minimum=1, maximum=20, example=8, description="عدد النتائج، الافتراضي 8")
     * )
     * ),
     *
     * @OA\Response(
     * response=200,
     * description="Students retrieved successfully",
     *
     * @OA\JsonContent(
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Students retrieved successfully"),
     * @OA\Property(
     * property="data", type="object",
     * @OA\Property(property="students", type="array", @OA\Items(type="object")),
     * @OA\Property(property="exact_match_id", type="integer", nullable=true, example=12, description="معرّف الطالب عند التطابق التام، وإلا null")
     * )
     * )
     * ),
     *
     * @OA\Response(response=422, description="Validation error")
     * )
     */
    public function searchStudents(
        SearchAssistantStudentsRequest $request,
        FindStudentsForCompanyAssistant $findStudents,
    ): JsonResponse {
        $data = $request->validated();
        $students = $findStudents->handle($data['query'], $data['limit'] ?? 8);

        return $this->successResponse([
            'students' => StudentCompanyAssistantStudentResource::collection($students)->resolve($request),
            'exact_match_id' => $findStudents->exactMatch($students, $data['query'])?->id,
        ], __('Students retrieved successfully'));
    }

    /**
     * @OA\Post(
     * path="/api/v1/ppuds/student-company-assistant/companies/suggest",
     * summary="Suggest matching companies for a student",
     * description="الخطوة الثانية. يقترح شركات مناسبة للطالب بناءً على تخصصه وتسجيله. إذا لم يُرسل registration_id يُستنتج تسجيل الطالب تلقائياً، وقد يعود تحذير في warnings. التسجيل يجب أن يكون في الفصل والسنة الحاليين وإلا رُفض الطلب بـ 422.",
     * tags={"Student Company Assistant"},
     * security={{"sanctum": {}}},
     *
     * @OA\RequestBody(
     * required=true,
     *
     * @OA\JsonContent(
     * required={"student_id"},
     *
     * @OA\Property(property="student_id", type="integer", example=12),
     * @OA\Property(property="registration_id", type="integer", nullable=true, example=45, description="اختياري؛ يُستنتج تلقائياً إن تُرك فارغاً"),
     * @OA\Property(property="limit", type="integer", minimum=1, maximum=10, example=5, description="عدد الاقتراحات، الافتراضي 5")
     * )
     * ),
     *
     * @OA\Response(
     * response=200,
     * description="Company suggestions generated successfully",
     *
     * @OA\JsonContent(
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(
     * property="data", type="object",
     * @OA\Property(property="student", type="object"),
     * @OA\Property(property="registration", type="object"),
     * @OA\Property(property="message", type="string", description="شرح نصي للاقتراحات يُعرض للمستخدم"),
     * @OA\Property(property="warnings", type="array", @OA\Items(type="string"), description="تحذيرات غير مانعة، مثل استنتاج تسجيل غير مؤكد"),
     * @OA\Property(property="used_ai", type="boolean", example=true, description="هل وُلِّدت الاقتراحات بالذكاء الاصطناعي أم بالمطابقة الاعتيادية"),
     * @OA\Property(property="suggestions", type="array", @OA\Items(type="object"))
     * )
     * )
     * ),
     *
     * @OA\Response(response=422, description="الطالب غير صالح، أو لا تسجيل له، أو تسجيله خارج الفصل الحالي")
     * )
     */
    public function suggestCompanies(
        SuggestAssistantCompaniesRequest $request,
        ResolveStudentCompanyRegistration $resolveRegistration,
        StudentCompanySuggestionService $suggestionService,
    ): JsonResponse {
        $data = $request->validated();
        $student = $this->student($data['student_id']);

        if (! $student) {
            return $this->errorResponse(__('The selected user is not a student.'), 422);
        }

        [$registration, $warning] = $this->registrationForStudent(
            $student,
            $data['registration_id'] ?? null,
            $resolveRegistration,
        );

        if (! $registration) {
            return $this->errorResponse($warning ?: __('No valid registration was found for this student.'), 422);
        }

        if ($response = $this->ensureRegistrationInCurrentSemester($registration)) {
            return $response;
        }

        $result = $suggestionService->suggest($student, $registration, $data['limit'] ?? 5);

        return $this->successResponse([
            'student' => StudentCompanyAssistantStudentResource::make($student)->resolve($request),
            'registration' => RegistrationResource::make($registration)->resolve($request),
            'message' => $result['message'],
            'warnings' => array_values(array_filter([$warning])),
            'used_ai' => (bool) ($result['used_ai'] ?? false),
            'suggestions' => StudentCompanyAssistantSuggestionResource::collection(
                collect($result['suggestions'] ?? [])
            )->resolve($request),
        ], __('Company suggestions generated successfully'));
    }

    /**
     * @OA\Post(
     * path="/api/v1/ppuds/student-company-assistant/companies/search",
     * summary="Search companies by name",
     * description="بحث حر عن شركة، يُستخدم حين يريد المستخدم اختيار شركة بنفسه بدل الاعتماد على الاقتراحات.",
     * tags={"Student Company Assistant"},
     * security={{"sanctum": {}}},
     *
     * @OA\RequestBody(
     * required=true,
     *
     * @OA\JsonContent(
     * required={"query"},
     *
     * @OA\Property(property="query", type="string", minLength=2, maxLength=180, example="بي فاوند"),
     * @OA\Property(property="limit", type="integer", minimum=1, maximum=20, example=8)
     * )
     * ),
     *
     * @OA\Response(
     * response=200,
     * description="Companies retrieved successfully",
     *
     * @OA\JsonContent(
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(
     * property="data", type="object",
     * @OA\Property(property="companies", type="array", @OA\Items(type="object"))
     * )
     * )
     * ),
     *
     * @OA\Response(response=422, description="Validation error")
     * )
     */
    public function searchCompanies(
        SearchAssistantCompaniesRequest $request,
        FindCompaniesForCompanyAssistant $findCompanies,
    ): JsonResponse {
        $data = $request->validated();
        $companies = $findCompanies->handle($data['query'], $data['limit'] ?? 8);

        return $this->successResponse([
            'companies' => StudentCompanyAssistantCompanyResource::collection($companies)->resolve($request),
        ], __('Companies retrieved successfully'));
    }

    /**
     * @OA\Post(
     * path="/api/v1/ppuds/student-company-assistant/link",
     * summary="Link one company to a student",
     * description="الخطوة الأخيرة. يربط شركة واحدة بتسجيل الطالب. العملية idempotent: إذا كان الطالب مرتبطاً بالشركة سابقاً يعود operation=already_exists بحالة 200 لا خطأ، فلا حاجة لمعالجة خاصة في الواجهة.",
     * tags={"Student Company Assistant"},
     * security={{"sanctum": {}}},
     *
     * @OA\RequestBody(
     * required=true,
     *
     * @OA\JsonContent(
     * required={"student_id", "registration_id", "company_id"},
     *
     * @OA\Property(property="student_id", type="integer", example=12),
     * @OA\Property(property="registration_id", type="integer", example=45),
     * @OA\Property(property="company_id", type="integer", example=3),
     * @OA\Property(property="branch_id", type="integer", nullable=true, example=2),
     * @OA\Property(property="department_id", type="integer", nullable=true, example=4),
     * @OA\Property(property="reason", type="string", nullable=true, maxLength=280, description="سبب الاختيار كما ظهر في الاقتراح"),
     * @OA\Property(property="fit_score", type="integer", nullable=true, minimum=1, maximum=100, example=87)
     * )
     * ),
     *
     * @OA\Response(
     * response=200,
     * description="Company linked to student successfully",
     *
     * @OA\JsonContent(
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(
     * property="data", type="object",
     * @OA\Property(property="operation", type="string", enum={"created", "updated", "already_exists"}, example="created"),
     * @OA\Property(property="student_company", type="object", nullable=true)
     * )
     * )
     * ),
     *
     * @OA\Response(response=422, description="التسجيل لا يخص هذا الطالب، أو الشركة غير موجودة، أو التسجيل خارج الفصل الحالي")
     * )
     */
    public function linkCompany(
        LinkAssistantCompanyRequest $request,
        FindCompaniesForCompanyAssistant $findCompanies,
        LinkSuggestedCompanyToStudent $linkCompany,
    ): JsonResponse {
        $data = $request->validated();
        $student = $this->student($data['student_id']);
        $registration = $this->registrationForStudentId($data['student_id'], $data['registration_id']);

        if (! $student || ! $registration) {
            return $this->errorResponse(__('The registration does not belong to this student.'), 422);
        }

        if ($response = $this->ensureRegistrationInCurrentSemester($registration)) {
            return $response;
        }

        $suggestion = $this->suggestionFromPayload($data, $findCompanies);

        if (! $suggestion) {
            return $this->errorResponse(__('The selected company was not found.'), 422);
        }

        $operation = $linkCompany->handle(
            $student->id,
            $registration->id,
            $suggestion,
            $request->user()->id,
        );

        $studentCompany = $this->linkedStudentCompany($registration->id, $suggestion['company_id']);
        $message = $operation === 'already_exists'
            ? __('Student is already assigned to this company.')
            : __('Company linked to student successfully');

        return $this->successResponse([
            'operation' => $operation,
            'student_company' => $studentCompany
                ? StudentCompanyResource::make($studentCompany)->resolve($request)
                : null,
        ], $message);
    }

    /**
     * @OA\Post(
     * path="/api/v1/ppuds/student-company-assistant/link-all",
     * summary="Link several companies to a student at once",
     * description="يربط حتى 10 شركات دفعة واحدة. الشركات التي تعذّر إيجادها تُتجاهَل بصمت ولا تُفشل الطلب، فقارن عدد ما أرسلته بمجموع linked_count + already_exists_count + updated_count لتعرف إن سقط شيء.",
     * tags={"Student Company Assistant"},
     * security={{"sanctum": {}}},
     *
     * @OA\RequestBody(
     * required=true,
     *
     * @OA\JsonContent(
     * required={"student_id", "registration_id", "companies"},
     *
     * @OA\Property(property="student_id", type="integer", example=12),
     * @OA\Property(property="registration_id", type="integer", example=45),
     * @OA\Property(
     * property="companies", type="array", minItems=1, maxItems=10,
     *
     * @OA\Items(
     * type="object",
     * required={"company_id"},
     *
     * @OA\Property(property="company_id", type="integer", example=3, description="لا يتكرر داخل المصفوفة"),
     * @OA\Property(property="branch_id", type="integer", nullable=true),
     * @OA\Property(property="department_id", type="integer", nullable=true),
     * @OA\Property(property="reason", type="string", nullable=true, maxLength=280),
     * @OA\Property(property="fit_score", type="integer", nullable=true, minimum=1, maximum=100)
     * )
     * )
     * )
     * ),
     *
     * @OA\Response(
     * response=200,
     * description="Companies processed successfully",
     *
     * @OA\JsonContent(
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(
     * property="data", type="object",
     * @OA\Property(property="linked_count", type="integer", example=2),
     * @OA\Property(property="already_exists_count", type="integer", example=1),
     * @OA\Property(property="updated_count", type="integer", example=0),
     * @OA\Property(property="items", type="array", @OA\Items(type="object"))
     * )
     * )
     * ),
     *
     * @OA\Response(response=422, description="التسجيل لا يخص هذا الطالب، أو خارج الفصل الحالي")
     * )
     */
    public function linkAllCompanies(
        LinkAllAssistantCompaniesRequest $request,
        FindCompaniesForCompanyAssistant $findCompanies,
        LinkSuggestedCompanyToStudent $linkCompany,
    ): JsonResponse {
        $data = $request->validated();
        $student = $this->student($data['student_id']);
        $registration = $this->registrationForStudentId($data['student_id'], $data['registration_id']);

        if (! $student || ! $registration) {
            return $this->errorResponse(__('The registration does not belong to this student.'), 422);
        }

        if ($response = $this->ensureRegistrationInCurrentSemester($registration)) {
            return $response;
        }

        $items = collect($data['companies'])
            ->map(function (array $companyPayload) use ($student, $registration, $findCompanies, $linkCompany, $request) {
                $suggestion = $this->suggestionFromPayload($companyPayload, $findCompanies);

                if (! $suggestion) {
                    return null;
                }

                $operation = $linkCompany->handle(
                    $student->id,
                    $registration->id,
                    $suggestion,
                    $request->user()->id,
                );

                $studentCompany = $this->linkedStudentCompany($registration->id, $suggestion['company_id']);

                return [
                    'operation' => $operation,
                    'student_company' => $studentCompany
                        ? StudentCompanyResource::make($studentCompany)->resolve($request)
                        : null,
                ];
            })
            ->filter()
            ->values();
        $operationCounts = $items->countBy('operation');

        return $this->successResponse([
            'linked_count' => $items->where('operation', 'created')->count(),
            'already_exists_count' => $operationCounts->get('already_exists', 0),
            'updated_count' => $operationCounts->get('updated', 0),
            'items' => $items->all(),
        ], __('Companies processed successfully'));
    }

    private function student(int $studentId): ?User
    {
        return User::query()
            ->with('studentProfile.major')
            ->whereHas('studentProfile')
            ->find($studentId);
    }

    private function registrationForStudent(
        User $student,
        ?int $registrationId,
        ResolveStudentCompanyRegistration $resolveRegistration,
    ): array {
        if ($registrationId) {
            return [
                $this->registrationForStudentId($student->id, $registrationId),
                null,
            ];
        }

        return $resolveRegistration->handle($student);
    }

    private function registrationForStudentId(int $studentId, int $registrationId): ?Registration
    {
        return Registration::query()
            ->with('course')
            ->where('student_id', $studentId)
            ->find($registrationId);
    }

    private function suggestionFromPayload(
        array $payload,
        FindCompaniesForCompanyAssistant $findCompanies,
    ): ?array {
        $company = $findCompanies->find((int) $payload['company_id']);

        if (! $company) {
            return null;
        }

        return array_merge(
            $findCompanies->toSuggestion($company, Arr::get($payload, 'reason')),
            Arr::only($payload, ['branch_id', 'department_id', 'fit_score'])
        );
    }

    private function linkedStudentCompany(int $registrationId, int $companyId): ?StudentCompany
    {
        return StudentCompany::query()
            ->with([
                'registration.course',
                'student.studentProfile.major',
                'company.branches.departments',
                'branch.departments',
                'workingHours',
                'branch.workingHours',
                'department',
            ])
            ->where('registration_id', $registrationId)
            ->where('company_id', $companyId)
            ->first();
    }
}
