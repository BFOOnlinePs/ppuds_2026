<?php

namespace Modules\PPUDS\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Modules\Core\Enums\UserRole;
use Modules\Core\Traits\ApiResponse;
use Modules\PPUDS\Entities\Survey;
use Modules\PPUDS\Entities\SurveyAnswer;
use Modules\PPUDS\Enums\SurveyQuestionType;
use Modules\PPUDS\Http\Requests\SurveyAnswerRequest;
use Modules\PPUDS\Services\CompanyEvaluationGradeService;
use Modules\PPUDS\Support\HandlesCompanySupervisorSurveyEvaluations;
use Modules\PPUDS\Support\SurveyAnswerFiles;
use Modules\PPUDS\Transformers\V1\SurveyAnswerResource;
use Spatie\QueryBuilder\QueryBuilder;

class SurveyAnswerController extends Controller
{
    use ApiResponse;
    use HandlesCompanySupervisorSurveyEvaluations;

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/survey-answers",
     * summary="Get survey answers",
     * description="Retrieve answers filtered by survey or user",
     * tags={"Survey Answers"},
     * security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     * name="filter[survey_id]",
     * in="query",
     * required=false,
     * description="Filter by Survey ID",
     *
     * @OA\Schema(type="integer")
     * ),
     *
     * @OA\Response(
     * response=200,
     * description="Answers retrieved successfully"
     * )
     * )
     */
    public function index()
    {
        $defaultPerPage = config('core.pagination.per_page', 10);
        $perPage = min(request('per_page', $defaultPerPage), 100);

        // Same roles that may view survey submissions on the web dashboard
        // (Survey\Details\Details::canViewSurveyDetails); everyone else only
        // sees the answers they submitted.
        $canViewAllAnswers = auth()->user()?->hasAnyRole([
            UserRole::SUPER_ADMIN->value,
            UserRole::ADMIN->value,
            UserRole::PRACTICAL_TRAINING_SUPERVISOR->value,
            'Academic Supervisor',
            'University Supervisor',
        ]) ?? false;

        $answers = QueryBuilder::for(SurveyAnswer::class)
            ->when(! $canViewAllAnswers, fn ($query) => $query->where('submitted_by', auth()->id()))
            ->allowedFields(SurveyAnswerResource::allowedFields())
            ->allowedFilters(SurveyAnswerResource::allowedFilters())
            ->allowedIncludes(SurveyAnswerResource::allowedIncludes())
            ->paginate($perPage);

        return $this->successResponse(
            SurveyAnswerResource::collection($answers),
            __('Answers retrieved successfully')
        );
    }

    /**
     * @OA\Post(
     * path="/api/v1/ppuds/survey-answers",
     * summary="Submit survey answers",
     * tags={"Survey Answers"},
     * security={{"sanctum": {}}},
     *
     * @OA\RequestBody(
     * required=true,
     *
     * @OA\JsonContent(
     * required={"survey_id", "answers"},
     *
     * @OA\Property(property="survey_id", type="integer", example=1),
     * @OA\Property(property="student_company_id", type="integer", nullable=true, example=10, description="Required when a company supervisor evaluates a student"),
     * @OA\Property(
     * property="answers",
     * type="array",
     *
     * @OA\Items(
     * type="object",
     * required={"question_id", "value"},
     *
     * @OA\Property(property="question_id", type="integer", example=10),
     * @OA\Property(
     * property="value",
     * description="Depends on the question type: RATING -> integer 1 to 5. RADIO/SELECT -> the selected option id (integer). CHECKBOX/MULTI_SELECT -> array of option ids. TEXT/TEXTAREA/DATE -> string. NUMBER -> number (0 or more). FILE -> send the request as multipart/form-data with the file itself in answers[i][file] (or answers[i][value]); a device path string is rejected with 422. Max 20MB.",
     * example="3"
     * )
     * )
     * )
     * )
     * ),
     *
     * @OA\Response(response=201, description="Submitted")
     * )
     */
    public function store(SurveyAnswerRequest $request)
    {
        $survey = Survey::with('questions')->findOrFail($request->survey_id);
        $userId = auth()->id();
        $studentCompany = null;

        $surveyId = $survey->id;

        if ($this->shouldEvaluateStudentsForSurvey($survey, auth()->user())) {
            $studentCompanyId = (int) $request->input('student_company_id');

            if (! $studentCompanyId) {
                return response()->json([
                    'status' => false,
                    'message' => __('Please select a student.'),
                ], 422);
            }

            $studentCompany = $this->currentSurveyStudentCompaniesQuery($survey, $userId)
                ->find($studentCompanyId);

            if (! $studentCompany) {
                return response()->json([
                    'status' => false,
                    'message' => __('Selected student is not available for evaluation'),
                ], 403);
            }

            if ($survey->hasBeenSubmittedBy($userId, $studentCompany->id)) {
                return response()->json([
                    'status' => false,
                    'message' => __('This student has already been evaluated for this survey'),
                ], 403);
            }
        } elseif ($this->shouldStudentEvaluateCompaniesForSurvey($survey, auth()->user())) {
            $studentCompanyId = (int) $request->input('student_company_id');

            if (! $studentCompanyId) {
                return response()->json([
                    'status' => false,
                    'message' => __('Please select a company.'),
                ], 422);
            }

            $studentCompany = $this->currentSurveyStudentCompaniesForStudentQuery($survey, $userId)
                ->find($studentCompanyId);

            if (! $studentCompany) {
                return response()->json([
                    'status' => false,
                    'message' => __('Selected company is not available for evaluation'),
                ], 403);
            }

            if ($survey->hasBeenSubmittedBy($userId, $studentCompany->id)) {
                return response()->json([
                    'status' => false,
                    'message' => __('This company has already been evaluated for this survey'),
                ], 403);
            }
        } elseif ($survey->hasBeenSubmittedBy($userId)) {
            return response()->json([
                'status' => false,
                'message' => __('You have already submitted this survey.'),
            ], 403);
        }

        // سؤال الرقم لا يقبل نصاً حتى يبقى عموده في ملف الإكسيل أرقاماً فقط.
        foreach ($request->answers as $answerData) {
            $value = $answerData['value'] ?? null;
            $question = $survey->questions->firstWhere('id', $answerData['question_id']);

            if ((int) $question?->type === SurveyQuestionType::NUMBER->value
                && ! (is_null($value) || $value === '')
                && (! is_numeric($value) || $value < 0)) {
                return response()->json([
                    'status' => false,
                    'message' => __('Please enter a number for: :question', ['question' => $question->content]),
                ], 422);
            }
        }

        $answers = $request->input('answers', []);

        // كل إجابة يجب أن تخص سؤالاً من هذا الاستبيان نفسه.
        $foreignQuestion = collect($answers)
            ->pluck('question_id')
            ->contains(fn ($id) => ! $survey->questions->contains('id', (int) $id));

        if ($foreignQuestion) {
            return response()->json([
                'status' => false,
                'message' => __('Some answers do not belong to this survey.'),
            ], 422);
        }

        [$fileError, $uploads] = $this->collectAnswerFiles($request, $survey, $answers);

        if ($fileError !== null) {
            return response()->json(['status' => false, 'message' => $fileError], 422);
        }

        $storedPaths = [];

        try {
            foreach ($uploads as $index => $file) {
                $storedPaths[] = $answers[$index]['value'] = $this->storeAnswerFile($file);
            }

            $this->saveAnswers($answers, $survey, $surveyId, $userId, $studentCompany);
        } catch (\Throwable $e) {
            // لا نترك ملفات يتيمة على القرص إن فشل الحفظ.
            Storage::disk(SurveyAnswerFiles::DISK)->delete(array_filter($storedPaths));

            throw $e;
        }

        // علامة الشركة تُشتق من إجابات التقييم، فتُحدَّث فور تسليمها.
        if ($studentCompany && $this->shouldEvaluateStudentsForSurvey($survey, auth()->user())) {
            app(CompanyEvaluationGradeService::class)->refreshFor($studentCompany);
        }

        return $this->successResponse(
            null,
            __('Answers submitted successfully'),
            201
        );
    }

    /**
     * يتحقق من ملفات أسئلة "رفع ملف": يجب أن يصل الملف نفسه (multipart) لا مساره
     * على جهاز الطالب، ملف واحد لكل سؤال، بنوع وحجم مسموحين، والسؤال الإلزامي لا يُترك بلا ملف.
     *
     * @return array{0: ?string, 1: array<int|string, UploadedFile>} رسالة الخطأ (إن وُجد) والملفات حسب فهرس الإجابة
     */
    private function collectAnswerFiles(SurveyAnswerRequest $request, Survey $survey, array $answers): array
    {
        $uploads = [];
        $answeredQuestionIds = [];

        foreach ($answers as $index => $answerData) {
            $question = $survey->questions->firstWhere('id', (int) ($answerData['question_id'] ?? 0));

            if ((int) $question?->type !== SurveyQuestionType::FILE->value) {
                continue;
            }

            $label = ['question' => $question->content];
            $file = $request->file("answers.{$index}.file") ?? $request->file("answers.{$index}.value");

            if (is_array($file) || isset($answeredQuestionIds[$question->id])) {
                return [__('Only one file can be uploaded for: :question', $label), []];
            }

            if (! $file instanceof UploadedFile) {
                // نص أو مسار بدل الملف: يُرفض حتى لا تُحفظ إجابة بلا ملف.
                if (filled($answerData['value'] ?? null)) {
                    return [__('Please attach the file itself for: :question', $label), []];
                }

                continue;
            }

            $validator = Validator::make(['file' => $file], ['file' => SurveyAnswerFiles::rules()]);

            if ($validator->fails()) {
                return [$question->content.': '.$validator->errors()->first('file'), []];
            }

            $uploads[$index] = $file;
            $answeredQuestionIds[$question->id] = true;
        }

        $missingRequired = $survey->questions->first(fn ($question): bool => (int) $question->type === SurveyQuestionType::FILE->value
            && $question->is_required
            && ! isset($answeredQuestionIds[$question->id]));

        if ($missingRequired) {
            return [__('Please upload a file for: :question', ['question' => $missingRequired->content]), []];
        }

        return [null, $uploads];
    }

    /**
     * يحفظ الملف على القرص الخاص باسمه الأصلي داخل مجلد فريد حتى لا تتصادم الأسماء.
     */
    private function storeAnswerFile(UploadedFile $file): string
    {
        $name = preg_replace('/[^\p{L}\p{N}._ -]+/u', '_', $file->getClientOriginalName());
        $name = trim((string) $name, ' ._') ?: 'file';

        if (! Str::contains($name, '.') && $file->guessExtension()) {
            $name .= '.'.$file->guessExtension();
        }

        $path = $file->storeAs(SurveyAnswerFiles::DIRECTORY.'/'.Str::uuid(), Str::limit($name, 150, ''), SurveyAnswerFiles::DISK);

        if ($path === false) {
            throw new \RuntimeException('Failed to store survey answer file.');
        }

        return $path;
    }

    private function saveAnswers(array $answers, Survey $survey, int $surveyId, int $userId, $studentCompany): void
    {
        DB::transaction(function () use ($answers, $survey, $surveyId, $userId, $studentCompany) {

            foreach ($answers as $answerData) {
                $questionId = $answerData['question_id'];
                $value = $answerData['value'] ?? null;

                if (is_null($value) || $value === '') {
                    continue;
                }

                $question = $survey->questions->firstWhere('id', $questionId);

                $isOptionType = in_array((int) $question?->type, [
                    SurveyQuestionType::RADIO->value,
                    SurveyQuestionType::CHECKBOX->value,
                    SurveyQuestionType::SELECT->value,
                    SurveyQuestionType::MULTI_SELECT->value,
                ]);

                if (is_array($value)) {
                    foreach ($value as $optionValue) {
                        SurveyAnswer::create([
                            'survey_id' => $surveyId,
                            'survey_question_id' => $questionId,
                            'selected_option_id' => $isOptionType ? $optionValue : null,
                            'text_answer' => $isOptionType ? null : $optionValue,
                            'submitted_by' => $userId,
                            'student_company_id' => $studentCompany?->id,
                            'evaluated_student_id' => $studentCompany?->student_id,
                        ]);
                    }
                } else {
                    SurveyAnswer::create([
                        'survey_id' => $surveyId,
                        'survey_question_id' => $questionId,
                        'selected_option_id' => $isOptionType ? (int) $value : null,
                        'text_answer' => $isOptionType ? null : $value,
                        'submitted_by' => $userId,
                        'student_company_id' => $studentCompany?->id,
                        'evaluated_student_id' => $studentCompany?->student_id,
                    ]);
                }
            }
        });
    }
}
