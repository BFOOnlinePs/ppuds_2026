<?php

namespace Modules\PPUDS\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Core\Traits\ApiResponse;
use Modules\PPUDS\Entities\Survey;
use Modules\PPUDS\Entities\SurveyAnswer;
use Modules\PPUDS\Http\Requests\SurveyAnswerRequest;
use Modules\PPUDS\Transformers\V1\SurveyAnswerResource;
use Spatie\QueryBuilder\QueryBuilder;

class SurveyAnswerController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/survey-answers",
     * summary="Get survey answers",
     * description="Retrieve answers filtered by survey or user",
     * tags={"Survey Answers"},
     * security={{"sanctum": {}}},
     * @OA\Parameter(
     * name="filter[survey_id]",
     * in="query",
     * required=false,
     * description="Filter by Survey ID",
     * @OA\Schema(type="integer")
     * ),
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
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"survey_id", "answers"},
     * @OA\Property(property="survey_id", type="integer", example=1),
     * @OA\Property(
     * property="answers",
     * type="array",
     * @OA\Items(
     * type="object",
     * required={"question_id", "value"},
     * @OA\Property(property="question_id", type="integer", example=10),
     * @OA\Property(property="value", type="string", example="Excellent")
     * )
     * )
     * )
     * ),
     * @OA\Response(response=201, description="Submitted")
     * )
     */
    public function store(SurveyAnswerRequest $request)
    {
        $survey = Survey::findOrFail($request->survey_id);
        $userId = auth()->id();

        $surveyId = $survey->id;

        if ($survey->hasBeenSubmittedBy($userId)) {
            return response()->json([
                'status' => false,
                'message' => __('You have already submitted this survey.'),
            ], 403);
        }

        DB::transaction(function () use ($request, $surveyId, $userId) {

            foreach ($request->answers as $answerData) {
                $questionId = $answerData['question_id'];
                $value = $answerData['value'];

                if (is_null($value) || $value === '') {
                    continue;
                }

                if (is_array($value)) {
                    foreach ($value as $optionId) {
                        SurveyAnswer::create([
                            'survey_id' => $surveyId,
                            'survey_question_id' => $questionId,
                            'selected_option_id' => $optionId,
                        ]);
                    }
                } else {
                    $data = [
                        'survey_id' => $surveyId,
                        'survey_question_id' => $questionId,
                    ];

                    if (is_numeric($value)) {
                        $data['selected_option_id'] = $value;
                    } else {
                        $data['text_answer'] = $value;
                    }

                    $data['submitted_by'] = $userId;

                    SurveyAnswer::create($data);
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
