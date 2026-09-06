<?php

namespace Modules\PPUDS\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Core\Traits\ApiResponse;
use Modules\PPUDS\Entities\Survey;
use Modules\PPUDS\Entities\SurveyAnswer;
use Modules\PPUDS\Enums\SurveyQuestionType;
use Modules\PPUDS\Http\Requests\SurveyAnswerRequest;
use Modules\PPUDS\Support\HandlesCompanySupervisorSurveyEvaluations;
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

        $answers = QueryBuilder::for(SurveyAnswer::class)
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
     * description="Depends on the question type: RATING -> integer 1 to 5. RADIO/SELECT -> the selected option id (integer). CHECKBOX/MULTI_SELECT -> array of option ids. TEXT/TEXTAREA/DATE -> string.",
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

        DB::transaction(function () use ($request, $survey, $surveyId, $userId, $studentCompany) {

            foreach ($request->answers as $answerData) {
                $questionId = $answerData['question_id'];
                $value = $answerData['value'];

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

        return $this->successResponse(
            null,
            __('Answers submitted successfully'),
            201
        );
    }
}
