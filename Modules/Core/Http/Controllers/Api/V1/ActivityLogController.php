<?php

namespace Modules\Core\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Modules\Core\Entities\User;
use Modules\Core\Traits\ApiResponse;
use Modules\Core\Transformers\V1\ActivityResource;
use Spatie\Activitylog\Models\Activity;
use Spatie\QueryBuilder\QueryBuilder;

class ActivityLogController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     * path="/api/v1/activities",
     * summary="Get all activity logs",
     * description="Retrieve a paginated list of all system activity logs with advanced filtering, sorting, and including relations.",
     * tags={"Activity Logs"},
     * security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     * name="fields[activities]",
     * in="query",
     * required=false,
     * description="Comma separated list of fields to be returned",
     *
     * @OA\Schema(type="string", example="id,log_name,description,event,created_at")
     * ),
     *
     * @OA\Parameter(
     * name="filter[log_name]",
     * in="query",
     * required=false,
     * description="Filter logs by log name",
     *
     * @OA\Schema(type="string", example="default")
     * ),
     *
     * @OA\Parameter(
     * name="filter[event]",
     * in="query",
     * required=false,
     * description="Filter logs by event type (created, updated, deleted)",
     *
     * @OA\Schema(type="string", example="created")
     * ),
     *
     * @OA\Parameter(
     * name="filter[causer_id]",
     * in="query",
     * required=false,
     * description="Filter logs by the user ID who caused the activity",
     *
     * @OA\Schema(type="integer", example=1)
     * ),
     *
     * @OA\Parameter(
     * name="include",
     * in="query",
     * required=false,
     * description="Include related models (e.g., causer, subject)",
     *
     * @OA\Schema(type="string", example="causer,subject")
     * ),
     *
     * @OA\Parameter(
     * name="sort",
     * in="query",
     * required=false,
     * description="Sort by field (prefix with '-' for descending)",
     *
     * @OA\Schema(type="string", example="-created_at")
     * ),
     *
     * @OA\Parameter(
     * name="per_page",
     * in="query",
     * required=false,
     * description="Number of items per page",
     *
     * @OA\Schema(type="integer", example=15)
     * ),
     *
     * @OA\Response(
     * response=200,
     * description="Activities retrieved successfully",
     *
     * @OA\JsonContent(
     * type="object",
     *
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Activities retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="array",
     *
     * @OA\Items(
     * type="object",
     *
     * @OA\Property(property="id", type="integer", example=1),
     * @OA\Property(property="log_name", type="string", example="default"),
     * @OA\Property(property="description", type="string", example="created"),
     * @OA\Property(property="event", type="string", example="created"),
     * @OA\Property(property="created_at", type="string", format="date-time")
     * )
     * )
     * )
     * ),
     *
     * @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index()
    {
        $defaultPerPage = config('core.pagination.per_page');
        $maxPerPage = config('core.pagination.max_per_page');
        $perPage = min(request('per_page', $defaultPerPage), $maxPerPage);

        $activities = QueryBuilder::for(Activity::class)
            ->allowedFields(ActivityResource::allowedFields())
            ->allowedSorts(ActivityResource::allowedSorts())
            ->allowedFilters(ActivityResource::allowedFilters())
            ->allowedIncludes(ActivityResource::allowedIncludes())
            ->paginate($perPage)
            ->appends(request()->query());

        return $this->successResponse(
            ActivityResource::collection($activities),
            __('Activities retrieved successfully')
        );
    }

    /**
     * @OA\Get(
     * path="/api/v1/activities/{id}",
     * summary="Get a single activity log",
     * description="Retrieve details of a specific activity log by its ID",
     * tags={"Activity Logs"},
     * security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     * name="id",
     * in="path",
     * required=true,
     * description="Activity ID",
     *
     * @OA\Schema(type="integer", example=1)
     * ),
     *
     * @OA\Parameter(
     * name="include",
     * in="query",
     * required=false,
     * description="Include related models (e.g., causer, subject)",
     *
     * @OA\Schema(type="string", example="causer,subject")
     * ),
     *
     * @OA\Response(
     * response=200,
     * description="Activity retrieved successfully",
     *
     * @OA\JsonContent(
     * type="object",
     *
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Activity retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="object",
     * @OA\Property(property="id", type="integer", example=1),
     * @OA\Property(property="log_name", type="string", example="default"),
     * @OA\Property(property="description", type="string", example="created")
     * )
     * )
     * ),
     *
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=404, description="Activity not found")
     * )
     */
    public function show($id)
    {
        $activity = QueryBuilder::for(Activity::class)
            ->allowedFields(ActivityResource::allowedFields())
            ->allowedIncludes(ActivityResource::allowedIncludes())
            ->findOrFail($id);

        return $this->successResponse(
            new ActivityResource($activity),
            __('Activity retrieved successfully')
        );
    }
}
