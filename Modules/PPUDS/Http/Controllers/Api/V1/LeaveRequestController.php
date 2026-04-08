<?php

namespace Modules\PPUDS\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Modules\Core\Traits\ApiResponse;
use Modules\PPUDS\Entities\LeaveRequest;
use Modules\PPUDS\Enums\LeaveRequestStatus;
use Modules\PPUDS\Http\Requests\LeaveRequestRequest;
use Modules\PPUDS\Http\Requests\LeaveRequestUpdate;
use Modules\PPUDS\Transformers\V1\LeaveRequestResource;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @OA\Tag(
 * name="Leave Requests",
 * description="API Endpoints for managing student leave requests (Absence & Hourly Leave)"
 * )
 */
class LeaveRequestController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/leave-requests",
     * summary="List all leave requests",
     * description="Retrieve a list of leave requests with filtering and sorting",
     * tags={"Leave Requests"},
     * security={{"sanctum": {}}},
     * @OA\Parameter(
     * name="filter[student_company_id]",
     * in="query",
     * description="Filter by Student Company ID",
     * @OA\Schema(type="integer")
     * ),
     * @OA\Parameter(
     * name="filter[type]",
     * in="query",
     * description="Filter by type (leave, absence)",
     * @OA\Schema(type="string")
     * ),
     * @OA\Response(
     * response=200,
     * description="Leave requests retrieved successfully",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/LeaveRequestResource"))
     * )
     * )
     * )
     */
    public function index()
    {
        $defaultPerPage = config('core.pagination.per_page', 10);
        $maxPerPage = config('core.pagination.max_per_page', 100);
        $perPage = min(request('per_page', $defaultPerPage), $maxPerPage);

        $leaveRequests = QueryBuilder::for(LeaveRequest::class)
            ->allowedFields(LeaveRequestResource::allowedFields())
            ->allowedFilters(LeaveRequestResource::allowedFilters())
            ->allowedSorts(LeaveRequestResource::allowedSorts())
            ->allowedIncludes(LeaveRequestResource::allowedIncludes())
            ->with(['media'])
            ->paginate($perPage)
            ->appends(request()->query());

        return $this->successResponse(
            LeaveRequestResource::collection($leaveRequests),
            __('Leave Requests retrieved successfully')
        );
    }

    /**
     * @OA\Post(
     * path="/api/v1/ppuds/leave-requests",
     * summary="Create a new leave request",
     * description="Submit a new request for absence or hourly leave",
     * tags={"Leave Requests"},
     * security={{"sanctum": {}}},
     * @OA\RequestBody(
     * required=true,
     * @OA\MediaType(
     * mediaType="multipart/form-data",
     * @OA\Schema(
     * required={"student_company_id", "type", "start_at", "end_at", "reason"},
     * @OA\Property(property="student_company_id", type="integer", example=1),
     * @OA\Property(property="type", type="string", enum={"leave", "absence"}, example="leave"),
     * @OA\Property(property="start_at", type="string", format="date-time", example="2024-05-20 08:00:00"),
     * @OA\Property(property="end_at", type="string", format="date-time", example="2024-05-20 10:00:00"),
     * @OA\Property(property="reason", type="string", example="Medical appointment"),
     * @OA\Property(
     * property="attachment_file",
     * type="string",
     * format="binary",
     * description="Optional supporting document (e.g., medical certificate)"
     * ),
     * @OA\Property(property="attachment", type="string", format="binary", description="Optional supporting document")
     * )
     * )
     * ),
     * @OA\Response(response=201, description="Created successfully")
     * )
     */
    public function store(LeaveRequestRequest $request)
    {
        $data = $request->validated();
        $data['created_by'] = auth()->id();
        $data['status'] = 'pending';

        $data['company_approval'] = LeaveRequestStatus::PENDING ?? LeaveRequestStatus::APPROVED;
        $data['university_approval'] = LeaveRequestStatus::PENDING ?? LeaveRequestStatus::APPROVED;

        $leaveRequest = LeaveRequest::create($data);


        if ($request->hasFile('attachment_file')) {
            $leaveRequest->addImage($request->file('attachment_file'));
        }

        return $this->successResponse(
            new LeaveRequestResource($leaveRequest),
            __('Leave Request created successfully'),
            201
        );
    }

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/leave-requests/{leaveRequest}",
     * summary="Get leave request details",
     * tags={"Leave Requests"},
     * security={{"sanctum": {}}},
     * @OA\Parameter(name="leaveRequest", in="path", required=true, @OA\Schema(type="integer")),
     * @OA\Response(response=200, description="Success")
     * )
     */
    public function show(LeaveRequest $leaveRequest)
    {
        $leaveRequest = QueryBuilder::for(LeaveRequest::class)
            ->where('id', $leaveRequest->id)
            ->allowedFields(LeaveRequestResource::allowedFields())
            ->allowedIncludes(LeaveRequestResource::allowedIncludes())
            ->firstOrFail();

        return $this->successResponse(
            new LeaveRequestResource($leaveRequest),
            __('Leave Request retrieved successfully')
        );
    }

    /**
     * @OA\Post(
     * path="/api/v1/ppuds/leave-requests/{leaveRequest}",
     * summary="Update leave request",
     * description="Update details. Use _method=PUT for multipart support.",
     * tags={"Leave Requests"},
     * security={{"sanctum": {}}},
     * @OA\Parameter(name="leaveRequest", in="path", required=true, @OA\Schema(type="integer")),
     * @OA\RequestBody(
     * @OA\MediaType(
     * mediaType="multipart/form-data",
     * @OA\Schema(
     * @OA\Property(property="_method", type="string", example="PUT"),
     * @OA\Property(property="reason", type="string"),
     * @OA\Property(property="attachment", type="string", format="binary")
     * )
     * )
     * ),
     * @OA\Response(response=200, description="Updated successfully")
     * )
     */
    public function update(LeaveRequestUpdate $request, LeaveRequest $leaveRequest)
    {
        $leaveRequest->update($request->validated());

        if ($request->hasFile('attachment_file')) {
            $leaveRequest->addImage($request->file('attachment_file'));
        }

        return $this->successResponse(
            new LeaveRequestResource($leaveRequest->refresh()),
            __('Leave Request updated successfully')
        );
    }
}
