<?php

namespace Modules\PPUDS\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Core\Traits\ApiResponse;
use Modules\PPUDS\Entities\WorkExperience; // تأكد من المسار حسب هيكلة الـ Modules لديك
use Modules\PPUDS\Http\Requests\WorkExperienceRequest;
use Modules\PPUDS\Transformers\V1\WorkExperienceResource;
use Spatie\QueryBuilder\QueryBuilder;

class WorkExperienceController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/work-experiences",
     * summary="Get all work experiences",
     * description="Retrieve a list of all alumni work experiences with filtering and sorting",
     * tags={"Work Experiences"},
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
     * name="filter[company_name]",
     * in="query",
     * required=false,
     * description="Filter by company name",
     *
     * @OA\Schema(type="string")
     * ),
     *
     * @OA\Response(
     * response=200,
     * description="Work experiences retrieved successfully",
     *
     * @OA\JsonContent(
     * type="object",
     *
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Work experiences retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="array",
     *
     * @OA\Items(ref="#/components/schemas/WorkExperienceResource")
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

        $workExperiences = QueryBuilder::for(WorkExperience::class)
            ->allowedFields(WorkExperienceResource::allowedFields())
            ->allowedFilters(WorkExperienceResource::allowedFilters())
            ->allowedSorts(WorkExperienceResource::allowedSorts())
            ->allowedIncludes(WorkExperienceResource::allowedIncludes())
            ->paginate($perPage)
            ->appends(request()->query());

        return $this->successResponse(
            WorkExperienceResource::collection($workExperiences),
            __('Work experiences retrieved successfully')
        );
    }

    /**
     * @OA\Post(
     * path="/api/v1/ppuds/work-experiences",
     * summary="Create work experience",
     * tags={"Work Experiences"},
     * security={{"sanctum": {}}},
     *
     * @OA\RequestBody(
     * required=true,
     *
     * @OA\MediaType(
     * mediaType="application/json",
     *
     * @OA\Schema(
     * required={"position", "sector", "start_date"},
     * @OA\Property(property="company_name", type="string", example="PPU"),
     * @OA\Property(property="position", type="string", example="Backend Developer"),
     * @OA\Property(property="sector", type="string", example="IT"),
     * @OA\Property(property="location", type="string", example="Hebron"),
     * @OA\Property(property="start_date", type="string", format="date", example="2023-01-01"),
     * @OA\Property(property="end_date", type="string", format="date", nullable=true, example="2024-01-01"),
     * @OA\Property(property="is_current", type="boolean", example=false),
     * @OA\Property(property="description", type="string", example="Developed APIs...")
     * )
     * )
     * ),
     *
     * @OA\Response(
     * response=201,
     * description="Created",
     *
     * @OA\JsonContent(
     * type="object",
     *
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Created successfully"),
     * @OA\Property(property="data", ref="#/components/schemas/WorkExperienceResource")
     * )
     * )
     * )
     */
    public function store(WorkExperienceRequest $request)
    {
        $workExperience = DB::transaction(function () use ($request) {
            $data = $request->validated();

            $data['user_id'] = auth()->id();

            $data['created_by'] = auth()->id();

            if (isset($data['is_current']) && $data['is_current']) {
                $data['end_date'] = null;
            }

            return WorkExperience::create($data);
        });

        return $this->successResponse(
            new WorkExperienceResource($workExperience),
            __('Work experience created successfully'),
            201
        );
    }

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/work-experiences/{id}",
     * summary="Get work experience details",
     * tags={"Work Experiences"},
     * security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     * name="id",
     * in="path",
     * required=true,
     * description="Work Experience ID",
     *
     * @OA\Schema(type="integer")
     * ),
     *
     * @OA\Response(
     * response=200,
     * description="Work experience retrieved successfully",
     *
     * @OA\JsonContent(
     * type="object",
     *
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Work experience retrieved successfully"),
     * @OA\Property(property="data", ref="#/components/schemas/WorkExperienceResource")
     * )
     * )
     * )
     */
    public function show(WorkExperience $workExperience)
    {
        $workExperienceDetails = QueryBuilder::for(WorkExperience::class)
            ->where('id', $workExperience->id)
            ->allowedFields(WorkExperienceResource::allowedFields())
            ->allowedIncludes(WorkExperienceResource::allowedIncludes())
            ->firstOrFail();

        return $this->successResponse(
            new WorkExperienceResource($workExperienceDetails),
            __('Work experience retrieved successfully')
        );
    }

    /**
     * @OA\Delete(
     * path="/api/v1/ppuds/work-experiences/{id}",
     * summary="Delete work experience",
     * tags={"Work Experiences"},
     * security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     * name="id",
     * in="path",
     * required=true,
     * description="Work Experience ID",
     *
     * @OA\Schema(type="integer")
     * ),
     *
     * @OA\Response(
     * response=200,
     * description="Deleted successfully",
     *
     * @OA\JsonContent(
     * type="object",
     *
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Work experience deleted successfully"),
     * @OA\Property(property="data", type="null", nullable=true, example=null)
     * )
     * ),
     * @OA\Response(
     * response=403,
     * description="Forbidden",
     * @OA\JsonContent(
     * @OA\Property(property="status", type="boolean", example=false),
     * @OA\Property(property="message", type="string", example="You are not authorized to delete this record")
     * )
     * )
     * )
     */
    public function destroy(WorkExperience $workExperience)
    {
        if ($workExperience->user_id !== auth()->id()) {
            return $this->errorResponse(__('You are not authorized to delete this record'), 403);
        }

        $workExperience->delete();

        return $this->successResponse(
            null,
            __('Work experience deleted successfully')
        );
    }
}
