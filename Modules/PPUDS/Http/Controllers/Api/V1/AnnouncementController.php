<?php

namespace Modules\PPUDS\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Core\Traits\ApiResponse;
use Modules\PPUDS\Entities\Announcement;
use Modules\PPUDS\Http\Requests\AnnouncementRequest;
use Modules\PPUDS\Services\PpudsNotificationService;
use Modules\PPUDS\Transformers\V1\AnnouncementResource;
use Spatie\QueryBuilder\QueryBuilder;

class AnnouncementController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/announcements",
     * summary="Get all announcements",
     * description="Retrieve a list of all announcements with filtering and sorting",
     * tags={"Announcements"},
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
     * name="filter[announcement_category_id]",
     * in="query",
     * required=false,
     * description="Filter by announcement category ID",
     *
     * @OA\Schema(type="integer", example=1)
     * ),
     *
     * @OA\Parameter(
     * name="filter[name]",
     * in="query",
     * required=false,
     * description="Filter by name",
     *
     * @OA\Schema(type="string")
     * ),
     *
     * @OA\Response(
     * response=200,
     * description="Announcements retrieved successfully",
     *
     * @OA\JsonContent(
     * type="object",
     *
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Announcements retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="array",
     *
     * @OA\Items(ref="#/components/schemas/AnnouncementResource")
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

        $companies = QueryBuilder::for(Announcement::class)
            ->allowedFields(AnnouncementResource::allowedFields())
            ->allowedFilters(AnnouncementResource::allowedFilters())
            ->allowedSorts(AnnouncementResource::allowedSorts())
            ->allowedIncludes(AnnouncementResource::allowedIncludes())
            ->with(['media', 'translations', 'category.translations'])
            ->paginate($perPage)
            ->appends(request()->query());

        return $this->successResponse(
            AnnouncementResource::collection($companies),
            __('Announcements retrieved successfully')
        );
    }

    /**
     * @OA\Post(
     * path="/api/v1/ppuds/announcements",
     * summary="Create announcement",
     * tags={"Announcements"},
     * security={{"sanctum": {}}},
     *
     * @OA\RequestBody(
     * required=true,
     *
     * @OA\MediaType(
     * mediaType="multipart/form-data",
     *
     * @OA\Schema(ref="#/components/schemas/AnnouncementResource")
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
     * @OA\Property(property="data", ref="#/components/schemas/AnnouncementResource")
     * )
     * )
     * )
     */
    public function store(AnnouncementRequest $request)
    {
        $announcment = DB::transaction(function () use ($request) {
            $data = $request->validated();

            $data['created_by'] = auth()->id();

            $announcment = Announcement::create($data);

            if ($request->hasFile('image')) {
                $announcment->addImage($request->file('image'));
            } elseif ($request->hasFile('media')) {
                $announcment->addImage($request->file('media'));
            }

            return $announcment;
        });

        $announcment->load(['media', 'translations', 'category.translations']);

        app(PpudsNotificationService::class)->announcementCreated($announcment);

        return $this->successResponse(
            new AnnouncementResource($announcment),
            __('Announcement created successfully'),
            201
        );
    }

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/announcements/{id}",
     * summary="Get announcement details",
     * tags={"Announcements"},
     * security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     * name="id",
     * in="path",
     * required=true,
     * description="Announcement ID",
     *
     * @OA\Schema(type="integer")
     * ),
     *
     * @OA\Response(
     * response=200,
     * description="Announcement retrieved successfully",
     *
     * @OA\JsonContent(
     * type="object",
     *
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Announcement retrieved successfully"),
     * @OA\Property(property="data", ref="#/components/schemas/AnnouncementResource")
     * )
     * )
     * )
     */
    public function show(Announcement $announcment)
    {
        $announcment = QueryBuilder::for(Announcement::class)
            ->where('id', $announcment->id)
            ->allowedFields(AnnouncementResource::allowedFields())
            ->with(['media', 'translations', 'category.translations'])
            ->firstOrFail();

        return $this->successResponse(
            new AnnouncementResource($announcment),
            __('Announcement retrieved successfully')
        );
    }
}
