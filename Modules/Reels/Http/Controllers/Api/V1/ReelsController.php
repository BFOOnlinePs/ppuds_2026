<?php

namespace Modules\Reels\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Support\Arr;
use Modules\Core\Traits\ApiResponse;
use Modules\Reels\Entities\Reel;
use Modules\Reels\Enums\ReelStatus;
use Modules\Reels\Http\Requests\ReelsRequest;
use Modules\Reels\Transformers\V1\ReelsResource;
use Spatie\QueryBuilder\QueryBuilder;

class ReelsController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     * path="/api/v1/reels/reels",
     * summary="List all Reels",
     * description="Retrieve a paginated list of reels with filtering, sorting, and including related media.",
     * tags={"Reels"},
     * security={{"sanctum": {}}},
     * @OA\Parameter(
     * name="Accept-Language",
     * in="header",
     * required=false,
     * description="Language header (ar or en)",
     * @OA\Schema(type="string", default="ar", enum={"ar", "en"})
     * ),
     * @OA\Parameter(
     * name="include",
     * in="query",
     * required=false,
     * description="Include related resources (e.g., 'media', 'user'). Comma-separated.",
     * @OA\Schema(type="string", example="media,user")
     * ),
     * @OA\Parameter(
     * name="filter[status]",
     * in="query",
     * required=false,
     * description="Filter by status (e.g., 1 for pending, 2 for approved)",
     * @OA\Schema(type="integer", example=1)
     * ),
     * @OA\Parameter(
     * name="sort",
     * in="query",
     * required=false,
     * description="Sort by fields (e.g., 'created_at', '-views_count'). Prefix with '-' for DESC.",
     * @OA\Schema(type="string", example="-created_at")
     * ),
     * @OA\Parameter(
     * name="page",
     * in="query",
     * description="Page number for pagination",
     * @OA\Schema(type="integer", example=1)
     * ),
     * @OA\Parameter(
     * name="per_page",
     * in="query",
     * description="Number of items per page",
     * @OA\Schema(type="integer", example=15)
     * ),
     * @OA\Response(
     * response=200,
     * description="Reels retrieved successfully",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Reels retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="array",
     * @OA\Items(
     * type="object",
     * @OA\Property(property="id", type="integer", example=1),
     * @OA\Property(property="description", type="string", example="Delicious burger promo"),
     * @OA\Property(property="status", type="integer", example=1),
     * @OA\Property(property="views_count", type="integer", example=150),
     * @OA\Property(property="video_url", type="string", example="https://bucket.s3.amazonaws.com/reels/1/video.mp4"),
     * @OA\Property(property="thumbnail_url", type="string", example="https://bucket.s3.amazonaws.com/reels/1/conversions/thumb.jpg"),
     * @OA\Property(property="created_at", type="string", format="date-time")
     * )
     * ),
     * @OA\Property(
     * property="meta",
     * type="object",
     * description="Pagination metadata",
     * @OA\Property(property="current_page", type="integer", example=1),
     * @OA\Property(property="last_page", type="integer", example=5),
     * @OA\Property(property="total", type="integer", example=50)
     * )
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index()
    {
        $defaultPerPage = config('core.pagination.per_page', 15);
        $maxPerPage = config('core.pagination.max_per_page', 100);
        $perPage = min(request('per_page', $defaultPerPage), $maxPerPage);

        $reels = QueryBuilder::for(Reel::class)
            ->allowedFields(ReelsResource::allowedFields())
            ->allowedFilters(ReelsResource::allowedFilters())
            ->allowedSorts(ReelsResource::allowedSorts())
            ->allowedIncludes(['media', 'user'])
            ->paginate($perPage)
            ->appends(request()->query());

        return $this->successResponse(
            ReelsResource::collection($reels),
            __('Reels retrieved successfully')
        );
    }

    /**
     * @OA\Get(
     * path="/api/v1/reels/reels/{reel}",
     * summary="Get a single Reel",
     * description="Retrieve details of a specific reel by ID",
     * tags={"Reels"},
     * security={{"sanctum": {}}},
     * @OA\Parameter(
     * name="Accept-Language",
     * in="header",
     * required=false,
     * description="Language header (ar or en)",
     * @OA\Schema(type="string", default="ar", enum={"ar", "en"})
     * ),
     * @OA\Parameter(
     * name="reel",
     * in="path",
     * required=true,
     * description="Reel ID",
     * @OA\Schema(type="integer", example=1)
     * ),
     * @OA\Parameter(
     * name="include",
     * in="query",
     * required=false,
     * description="Include related resources (e.g., 'media', 'user')",
     * @OA\Schema(type="string", example="media")
     * ),
     * @OA\Response(
     * response=200,
     * description="Reel retrieved successfully",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Reel retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="object",
     * @OA\Property(property="id", type="integer", example=1),
     * @OA\Property(property="description", type="string", example="Amazing pizza"),
     * @OA\Property(property="status", type="integer", example=1),
     * @OA\Property(property="video_url", type="string", example="..."),
     * @OA\Property(property="created_at", type="string", format="date-time")
     * )
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=404, description="Reel not found")
     * )
     */
    public function show(Reel $reel)
    {
        $reel = QueryBuilder::for(Reel::class)
            ->where('id', $reel->id)
            ->allowedIncludes(['media', 'user'])
            ->firstOrFail();

        return $this->successResponse(
            new ReelsResource($reel),
            __('Reel retrieved successfully')
        );
    }

    /**
     * @OA\Post(
     * path="/api/v1/reels/reels",
     * summary="Create a new Reel",
     * description="Upload a new video reel with description.",
     * tags={"Reels"},
     * security={{"sanctum": {}}},
     * @OA\Parameter(
     * name="Accept-Language",
     * in="header",
     * required=true,
     * description="Language header (ar or en)",
     * @OA\Schema(type="string", default="ar", example="en")
     * ),
     * @OA\Parameter(
     * name="include",
     * in="query",
     * required=false,
     * description="Include related resources in the response immediately (e.g., 'media', 'user').",
     * @OA\Schema(type="string", example="media,user")
     * ),
     * @OA\RequestBody(
     * required=true,
     * @OA\MediaType(
     * mediaType="multipart/form-data",
     * @OA\Schema(
     * required={"video", "user_id", "status"},
     * @OA\Property(
     * property="user_id",
     * type="integer",
     * example=1,
     * description="The ID of the user owning this reel"
     * ),
     * @OA\Property(
     * property="video",
     * type="string",
     * format="binary",
     * description="The video file (mp4, mov, etc). Max size 50MB."
     * ),
     * @OA\Property(
     * property="status",
     * type="integer",
     * example=1,
     * description="The status of the reel (e.g. 1 for pending)"
     * ),
     * @OA\Property(
     * property="description",
     * type="string",
     * example="New delicious burger offer!",
     * description="Optional caption for the reel"
     * ),
     * @OA\Property(
     * property="sort_order",
     * type="integer",
     * example=0,
     * description="Optional sort order"
     * ),
     * )
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Reel created successfully",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Reel created successfully"),
     * @OA\Property(
     * property="data",
     * type="object",
     * @OA\Property(property="id", type="integer", example=15),
     * @OA\Property(property="description", type="string", example="New delicious burger offer!"),
     * @OA\Property(property="status", type="integer", example=1),
     * @OA\Property(property="video_url", type="string", example="http://domain.com/storage/1/video.mp4"),
     * @OA\Property(property="thumbnail_url", type="string", example="http://domain.com/storage/1/conversions/thumb.jpg"),
     * @OA\Property(property="created_at", type="string", format="date-time")
     * )
     * )
     * ),
     * @OA\Response(response=422, description="Validation Error"),
     * @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function store(ReelsRequest $request)
    {
        $validated = $request->validated();

        $reel = Reel::create(Arr::except($validated, ['video']));

        if ($request->hasFile('video')) {
            $reel->addMediaFromRequest('video')
                ->toMediaCollection('reels_video');
        }

        $reel = QueryBuilder::for(Reel::class)
            ->where('id', $reel->id)
            ->allowedIncludes(['media', 'user'])
            ->first();

        return $this->successResponse(
            new ReelsResource($reel),
            __('Reel created successfully')
        );
    }

    public function update(ReelsRequest $request, Reel $reel)
    {
        // Add logic here when needed
    }


    /**
     * @OA\Delete(
     * path="/api/v1/reels/reels/{reel}",
     * summary="Delete a Reel",
     * description="Delete a specific reel by ID. Only the owner or admin can delete.",
     * tags={"Reels"},
     * security={{"sanctum": {}}},
     * @OA\Parameter(
     * name="Accept-Language",
     * in="header",
     * required=false,
     * description="Language header (ar or en)",
     * @OA\Schema(type="string", default="ar", enum={"ar", "en"})
     * ),
     * @OA\Parameter(
     * name="reel",
     * in="path",
     * required=true,
     * description="Reel ID",
     * @OA\Schema(type="integer", example=1)
     * ),
     * @OA\Response(
     * response=200,
     * description="Reel deleted successfully",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Reel deleted successfully"),
     * @OA\Property(property="data", type="null", example=null)
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=403, description="Unauthorized - Only owner or admin can delete"),
     * @OA\Response(response=404, description="Reel not found")
     * )
     */

    public function destroy(Reel $reel)
    {
        $reel->clearMediaCollection('reels_video');

        $reel->delete();

        return $this->successResponse(
            null,
            __('Reel deleted successfully')
        );
    }
}
