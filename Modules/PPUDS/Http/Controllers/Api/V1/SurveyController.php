<?php

namespace Modules\PPUDS\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Core\Traits\ApiResponse;
use Modules\PPUDS\Entities\Survey;
use Modules\PPUDS\Http\Requests\SurveyRequest; // تأكد من إنشاء هذا الريكويست
use Modules\PPUDS\Services\PpudsNotificationService;
use Modules\PPUDS\Support\HandlesCompanySupervisorSurveyEvaluations;
use Modules\PPUDS\Transformers\V1\SurveyEvaluationStudentResource;
use Modules\PPUDS\Transformers\V1\SurveyResource;
use Spatie\QueryBuilder\QueryBuilder;

class SurveyController extends Controller
{
    use ApiResponse;
    use HandlesCompanySupervisorSurveyEvaluations;

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/surveys",
     * summary="Get all surveys",
     * description="Retrieve a list of all surveys with pagination",
     * tags={"Surveys"},
     * security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     * name="Accept-Language",
     * in="header",
     * required=true,
     * description="Language header (ar or en)",
     *
     * @OA\Schema(type="string", default="ar", example="en")
     * ),
     *
     * @OA\Parameter(
     * name="include",
     * in="query",
     * required=false,
     * description="Include relations (e.g. questions, questions.options)",
     *
     * @OA\Schema(type="string", example="questions")
     * ),
     *
     * @OA\Response(
     * response=200,
     * description="Surveys retrieved successfully",
     *
     * @OA\JsonContent(
     * type="object",
     *
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Surveys retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="array",
     *
     * @OA\Items(
     * type="object",
     *
     * @OA\Property(property="id", type="integer", example=1),
     * @OA\Property(property="title", type="string", example="Student Satisfaction Survey"),
     * @OA\Property(property="description", type="string", example="Survey about university services"),
     * @OA\Property(property="serve_group", type="string", example="students"),
     * @OA\Property(property="is_active", type="boolean", example=true),
     * @OA\Property(property="created_at", type="string", format="date-time")
     * )
     * )
     * )
     * )
     * )
     */
    public function index()
    {
        $defaultPerPage = config('core.pagination.per_page', 10);
        $maxPerPage = config('core.pagination.max_per_page', 100);
        $perPage = min(request('per_page', $defaultPerPage), $maxPerPage);

        $surveys = QueryBuilder::for(Survey::class)
            ->allowedFields(SurveyResource::allowedFields())
            ->allowedFilters(SurveyResource::allowedFilters())
            ->allowedSorts(SurveyResource::allowedSorts())
            ->allowedIncludes(SurveyResource::allowedIncludes())
            ->with(['translations'])
            ->withSubmissionStatus()
            ->where('is_active', true)
            ->paginate($perPage)
            ->appends(request()->query());

        return $this->successResponse(
            SurveyResource::collection($surveys),
            __('Surveys retrieved successfully')
        );
    }

    /**
     * @OA\Post(
     * path="/api/v1/ppuds/surveys",
     * summary="Create a new survey",
     * description="Creates a survey with nested questions and options.",
     * tags={"Surveys"},
     * security={{"sanctum": {}}},
     *
     * @OA\RequestBody(
     * required=true,
     *
     * @OA\MediaType(
     * mediaType="application/json",
     *
     * @OA\Schema(
     * required={"title", "serve_group", "questions"},
     *
     * @OA\Property(property="title", type="string", example="General Survey"),
     * @OA\Property(property="description", type="string", example="Description here..."),
     * @OA\Property(property="serve_group", type="string", example="students"),
     * @OA\Property(property="start_date", type="string", format="date-time", example="2023-10-01 00:00:00"),
     * @OA\Property(property="end_date", type="string", format="date-time", example="2023-12-31 23:59:59"),
     * @OA\Property(property="is_active", type="boolean", example=true),
     * @OA\Property(
     * property="questions",
     * type="array",
     *
     * @OA\Items(
     * type="object",
     * required={"content", "type"},
     *
     * @OA\Property(property="content", type="string", example="How satisfied are you?"),
     * @OA\Property(property="type", type="string", enum={"text", "radio", "checkbox", "textarea"}, example="radio"),
     * @OA\Property(property="is_required", type="boolean", example=true),
     * @OA\Property(property="sort_order", type="integer", example=1),
     * @OA\Property(
     * property="options",
     * type="array",
     * description="Required if type is radio or checkbox",
     *
     * @OA\Items(
     * type="object",
     * required={"content"},
     *
     * @OA\Property(property="content", type="string", example="Very Satisfied"),
     * @OA\Property(property="sort_order", type="integer", example=1)
     * )
     * )
     * )
     * )
     * )
     * )
     * ),
     *
     * @OA\Response(response=201, description="Survey created successfully")
     * )
     */
    public function store(SurveyRequest $request)
    {
        $survey = DB::transaction(function () use ($request) {

            $surveyData = $request->safe()->except(['questions']);
            $surveyData['created_by'] = auth()->id();

            $survey = Survey::create($surveyData);

            // 2. إنشاء الأسئلة
            if ($request->has('questions')) {
                foreach ($request->questions as $questionData) {

                    $optionsData = $questionData['options'] ?? [];

                    // تحضير بيانات السؤال
                    $questionAttributes = collect($questionData)
                        ->except(['options'])
                        ->toArray();

                    // إنشاء السؤال مربوطاً بالاستبيان
                    $question = $survey->questions()->create($questionAttributes);

                    // 3. إنشاء الخيارات (إذا وجدت)
                    if (! empty($optionsData) && in_array($questionData['type'], ['radio', 'checkbox', 'select'])) {
                        foreach ($optionsData as $optionData) {
                            $question->options()->create([
                                'content' => $optionData['content'],
                                'sort_order' => $optionData['sort_order'] ?? 0,
                                'created_by' => auth()->id(),
                            ]);
                        }
                    }
                }
            }

            return $survey;
        });

        // إعادة تحميل العلاقات لضمان ظهور كل شيء في الرد
        $survey->load(['translations', 'questions.translations', 'questions.options.translations']);

        app(PpudsNotificationService::class)->surveyCreated($survey);

        return $this->successResponse(
            new SurveyResource($survey),
            __('Survey created successfully'),
            201
        );
    }

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/surveys/{survey}",
     * summary="Get a single survey",
     * description="Retrieve details of a specific survey including questions and options",
     * tags={"Surveys"},
     * security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     * name="survey",
     * in="path",
     * required=true,
     * description="Survey ID",
     *
     * @OA\Schema(type="integer", example=1)
     * ),
     *
     * @OA\Parameter(
     * name="Accept-Language",
     * in="header",
     * required=true,
     * description="Language header (ar or en)",
     *
     * @OA\Schema(type="string", default="ar", example="en")
     * ),
     *
     * @OA\Parameter(
     * name="include",
     * in="query",
     * required=false,
     * description="Include relations (e.g. questions, questions.options)",
     *
     * @OA\Schema(type="string", example="questions,questions.options")
     * ),
     *
     * @OA\Response(
     * response=200,
     * description="Survey retrieved successfully",
     *
     * @OA\JsonContent(
     * type="object",
     *
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Survey retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="object",
     * @OA\Property(property="id", type="integer", example=1),
     * @OA\Property(property="title", type="string", example="Survey Title"),
     * @OA\Property(
     * property="questions",
     * type="array",
     *
     * @OA\Items(type="object", description="Question Object")
     * )
     * )
     * )
     * ),
     *
     * @OA\Response(response=404, description="Survey not found")
     * )
     */
    public function show(Survey $survey)
    {
        $survey = QueryBuilder::for(Survey::class)
            ->where('id', $survey->id)
            ->allowedFields(SurveyResource::allowedFields())
            ->allowedIncludes(SurveyResource::allowedIncludes())
            ->with(['translations'])
            ->withSubmissionStatus()
            ->firstOrFail();

        return $this->successResponse(
            new SurveyResource($survey),
            __('Survey retrieved successfully')
        );
    }

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/surveys/{survey}/evaluation-students",
     * summary="Get company supervisor students for survey evaluation",
     * description="Return students assigned to the authenticated company supervisor for this survey.",
     * tags={"Surveys"},
     * security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     * name="survey",
     * in="path",
     * required=true,
     * description="Survey ID",
     *
     * @OA\Schema(type="integer", example=1)
     * ),
     *
     * @OA\Parameter(
     * name="status",
     * in="query",
     * required=false,
     * description="Filter students by evaluation status: pending, evaluated, all",
     *
     * @OA\Schema(type="string", enum={"pending", "evaluated", "all"}, default="pending")
     * ),
     *
     * @OA\Response(response=200, description="Evaluation students retrieved successfully"),
     * @OA\Response(response=403, description="Survey is not available for company supervisor evaluation")
     * )
     */
    public function evaluationStudents(Request $request, Survey $survey)
    {
        $user = $request->user();

        if (! $this->shouldEvaluateStudentsForSurvey($survey, $user)) {
            return $this->errorResponse(__('This survey is not available for company supervisor evaluation.'), 403);
        }

        $status = $request->query('status', 'pending');
        $baseQuery = $this->currentSurveyStudentCompaniesQuery($survey, $user->id)
            ->withExists([
                'surveyAnswers as is_evaluated' => fn (Builder $query) => $query
                    ->where('survey_id', $survey->id)
                    ->where('submitted_by', $user->id),
            ]);

        $totalStudents = (clone $baseQuery)->count();
        $evaluatedStudents = (clone $baseQuery)
            ->whereHas(
                'surveyAnswers',
                fn (Builder $query) => $query
                    ->where('survey_id', $survey->id)
                    ->where('submitted_by', $user->id)
            )
            ->count();
        $pendingStudents = max($totalStudents - $evaluatedStudents, 0);

        $studentCompanies = match ($status) {
            'all' => $baseQuery,
            'evaluated' => $baseQuery->whereHas(
                'surveyAnswers',
                fn (Builder $query) => $query
                    ->where('survey_id', $survey->id)
                    ->where('submitted_by', $user->id)
            ),
            default => $this->pendingStudentCompaniesForSupervisorQuery($survey, $user->id)
                ->withExists([
                    'surveyAnswers as is_evaluated' => fn (Builder $query) => $query
                        ->where('survey_id', $survey->id)
                        ->where('submitted_by', $user->id),
                ]),
        };

        $studentCompanies = $studentCompanies
            ->orderBy('id')
            ->get();

        return $this->successResponse([
            'survey_id' => $survey->id,
            'status' => $status,
            'total_students' => $totalStudents,
            'pending_students' => $pendingStudents,
            'evaluated_students' => $evaluatedStudents,
            'students' => SurveyEvaluationStudentResource::collection($studentCompanies)->resolve($request),
        ], __('Evaluation students retrieved successfully'));
    }

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/surveys/{survey}/evaluation-companies",
     * summary="Get companies for a student to evaluate on this survey",
     * description="Return company placements assigned to the authenticated student for this survey (student evaluates their training company).",
     * tags={"Surveys"},
     * security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     * name="survey",
     * in="path",
     * required=true,
     * description="Survey ID",
     *
     * @OA\Schema(type="integer", example=1)
     * ),
     *
     * @OA\Parameter(
     * name="status",
     * in="query",
     * required=false,
     * description="Filter companies by evaluation status: pending, evaluated, all",
     *
     * @OA\Schema(type="string", enum={"pending", "evaluated", "all"}, default="pending")
     * ),
     *
     * @OA\Response(response=200, description="Evaluation companies retrieved successfully"),
     * @OA\Response(response=403, description="Survey is not available for student company evaluation")
     * )
     */
    public function evaluationCompanies(Request $request, Survey $survey)
    {
        $user = $request->user();

        if (! $this->shouldStudentEvaluateCompaniesForSurvey($survey, $user)) {
            return $this->errorResponse(__('This survey is not available for student company evaluation.'), 403);
        }

        $status = $request->query('status', 'pending');
        $baseQuery = $this->currentSurveyStudentCompaniesForStudentQuery($survey, $user->id)
            ->withExists([
                'surveyAnswers as is_evaluated' => fn (Builder $query) => $query
                    ->where('survey_id', $survey->id)
                    ->where('submitted_by', $user->id),
            ]);

        $totalCompanies = (clone $baseQuery)->count();
        $evaluatedCompanies = (clone $baseQuery)
            ->whereHas(
                'surveyAnswers',
                fn (Builder $query) => $query
                    ->where('survey_id', $survey->id)
                    ->where('submitted_by', $user->id)
            )
            ->count();
        $pendingCompanies = max($totalCompanies - $evaluatedCompanies, 0);

        $studentCompanies = match ($status) {
            'all' => $baseQuery,
            'evaluated' => $baseQuery->whereHas(
                'surveyAnswers',
                fn (Builder $query) => $query
                    ->where('survey_id', $survey->id)
                    ->where('submitted_by', $user->id)
            ),
            default => $this->pendingStudentCompaniesForStudentSurveyQuery($survey, $user->id)
                ->withExists([
                    'surveyAnswers as is_evaluated' => fn (Builder $query) => $query
                        ->where('survey_id', $survey->id)
                        ->where('submitted_by', $user->id),
                ]),
        };

        $studentCompanies = $studentCompanies
            ->orderBy('id')
            ->get();

        return $this->successResponse([
            'survey_id' => $survey->id,
            'status' => $status,
            'total_companies' => $totalCompanies,
            'pending_companies' => $pendingCompanies,
            'evaluated_companies' => $evaluatedCompanies,
            'companies' => SurveyEvaluationStudentResource::collection($studentCompanies)->resolve($request),
        ], __('Evaluation companies retrieved successfully'));
    }
}
