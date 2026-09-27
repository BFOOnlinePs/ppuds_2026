<?php

namespace Modules\Core\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Modules\Core\Entities\User;
use Modules\Core\Traits\ApiResponse;
use Modules\Core\Transformers\V1\NotificationResource;
use Spatie\QueryBuilder\QueryBuilder;

class NotificationController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     * path="/api/v1/notifications",
     * summary="Get the authenticated user's notifications",
     * description="Retrieve a paginated list of the authenticated user's notifications with filtering and sorting.",
     * tags={"Notifications"},
     * security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     * name="filter[unread]",
     * in="query",
     * required=false,
     * description="Filter by read state (true = unread only, false = read only)",
     *
     * @OA\Schema(type="boolean", example=true)
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
     * description="Notifications retrieved successfully",
     *
     * @OA\JsonContent(
     * type="object",
     *
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Notifications retrieved successfully")
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

        $notifications = QueryBuilder::for(DatabaseNotification::class)
            ->allowedFields(NotificationResource::allowedFields())
            ->allowedSorts(NotificationResource::allowedSorts())
            ->allowedFilters(NotificationResource::allowedFilters())
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', auth()->id())
            ->latest()
            ->paginate($perPage)
            ->appends(request()->query());

        return $this->successResponse(
            NotificationResource::collection($notifications),
            __('Notifications retrieved successfully')
        );
    }

    /**
     * @OA\Get(
     * path="/api/v1/notifications/unread-count",
     * summary="Get the authenticated user's unread notifications count",
     * tags={"Notifications"},
     * security={{"sanctum": {}}},
     *
     * @OA\Response(
     * response=200,
     * description="Unread count retrieved successfully",
     *
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Unread count retrieved successfully"),
     * @OA\Property(property="data", type="object", @OA\Property(property="unread_count", type="integer", example=3))
     * )
     * ),
     *
     * @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function unreadCount()
    {
        $unreadCount = auth()->user()->unreadNotifications()->count();

        return $this->successResponse(
            ['unread_count' => $unreadCount],
            __('Unread count retrieved successfully')
        );
    }

    /**
     * @OA\Patch(
     * path="/api/v1/notifications/read-all",
     * summary="Mark all of the authenticated user's notifications as read",
     * tags={"Notifications"},
     * security={{"sanctum": {}}},
     *
     * @OA\Response(response=200, description="Notifications marked as read successfully"),
     * @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function markAllAsRead()
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);

        return $this->successResponse(
            null,
            __('Notifications marked as read successfully')
        );
    }

    /**
     * @OA\Patch(
     * path="/api/v1/notifications/settings",
     * summary="Enable or disable push notifications for the authenticated user",
     * description="Toggles whether push notifications (FCM) are sent to this user. In-app notifications (notification center) are not affected and keep working regardless.",
     * tags={"Notifications"},
     * security={{"sanctum": {}}},
     *
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"notifications_enabled"},
     * @OA\Property(property="notifications_enabled", type="boolean", example=false)
     * )
     * ),
     *
     * @OA\Response(
     * response=200,
     * description="Notification settings updated successfully",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Notification settings updated successfully"),
     * @OA\Property(property="data", type="object", @OA\Property(property="notifications_enabled", type="boolean", example=false))
     * )
     * ),
     *
     * @OA\Response(response=422, description="Validation errors"),
     * @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'notifications_enabled' => ['required', 'boolean'],
        ]);

        $user = auth()->user();
        $user->update(['notifications_enabled' => $data['notifications_enabled']]);

        return $this->successResponse(
            ['notifications_enabled' => $user->notifications_enabled],
            __('Notification settings updated successfully')
        );
    }

    /**
     * @OA\Get(
     * path="/api/v1/notifications/{notification}",
     * summary="Get a single notification",
     * tags={"Notifications"},
     * security={{"sanctum": {}}},
     *
     * @OA\Parameter(name="notification", in="path", required=true, description="Notification ID", @OA\Schema(type="string")),
     *
     * @OA\Response(response=200, description="Notification retrieved successfully"),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=404, description="Notification not found")
     * )
     */
    public function show($id)
    {
        $notification = $this->findForCurrentUser($id);

        return $this->successResponse(
            new NotificationResource($notification),
            __('Notification retrieved successfully')
        );
    }

    /**
     * @OA\Patch(
     * path="/api/v1/notifications/{notification}/read",
     * summary="Mark a single notification as read",
     * tags={"Notifications"},
     * security={{"sanctum": {}}},
     *
     * @OA\Parameter(name="notification", in="path", required=true, description="Notification ID", @OA\Schema(type="string")),
     *
     * @OA\Response(response=200, description="Notification marked as read successfully"),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=404, description="Notification not found")
     * )
     */
    public function markAsRead($id)
    {
        $notification = $this->findForCurrentUser($id);
        $notification->markAsRead();

        return $this->successResponse(
            new NotificationResource($notification),
            __('Notification marked as read successfully')
        );
    }

    /**
     * @OA\Delete(
     * path="/api/v1/notifications/{notification}",
     * summary="Delete a notification",
     * tags={"Notifications"},
     * security={{"sanctum": {}}},
     *
     * @OA\Parameter(name="notification", in="path", required=true, description="Notification ID", @OA\Schema(type="string")),
     *
     * @OA\Response(response=200, description="Notification deleted successfully"),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=404, description="Notification not found")
     * )
     */
    public function destroy($id)
    {
        $notification = $this->findForCurrentUser($id);
        $notification->delete();

        return $this->successResponse(
            null,
            __('Notification deleted successfully')
        );
    }

    protected function findForCurrentUser(string $id): DatabaseNotification
    {
        return DatabaseNotification::query()
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', auth()->id())
            ->findOrFail($id);
    }
}
