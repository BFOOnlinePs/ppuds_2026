<?php

namespace Modules\PPUDS\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Core\Entities\User;
use Modules\Core\Traits\ApiResponse;
use Modules\PPUDS\Http\Requests\ConversationRequest;
use Modules\PPUDS\Services\ChatContactService;
use Modules\PPUDS\Services\PpudsNotificationService;
use Modules\PPUDS\Transformers\V1\ConversationResource;
use Modules\PPUDS\Transformers\V1\MessageResource;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\QueryBuilder;
use Wirechat\Wirechat\Events\MessageCreated;
use Wirechat\Wirechat\Models\Conversation;

class ChatController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/chats",
     * summary="Get all user conversations",
     * description="Retrieve a list of all conversations for the authenticated user",
     * tags={"Chat"},
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
     * description="Include relations (last_message, participants)",
     *
     * @OA\Schema(type="string", example="last_message")
     * ),
     *
     * @OA\Response(
     * response=200,
     * description="Conversations retrieved successfully",
     *
     * @OA\JsonContent(
     * type="object",
     *
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Conversations retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="array",
     *
     * @OA\Items(
     * type="object",
     *
     * @OA\Property(property="id", type="integer", example=1),
     * @OA\Property(property="type", type="string", example="private"),
     * @OA\Property(property="unread_count", type="integer", example=5),
     * @OA\Property(property="last_message_at", type="string", format="date-time")
     * )
     * )
     * )
     * )
     * )
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $conversations = QueryBuilder::for(Conversation::class)
            ->whereHas('participants', function ($query) use ($user) {
                $query->where('participantable_id', $user->getKey())
                    ->where('participantable_type', $user->getMorphClass());
            })
            ->with(['participants.participantable', 'lastMessage.attachment', 'lastMessage.sendable', 'group'])
            ->allowedIncludes(ConversationResource::allowedIncludes())
            ->allowedSorts(ConversationResource::allowedSorts())
            ->defaultSort('-updated_at')
            ->paginate(request('per_page', 15));

        return $this->successResponse(
            ConversationResource::collection($conversations),
            __('Conversations retrieved successfully')
        );
    }

    public function contacts(Request $request)
    {
        $contacts = app(ChatContactService::class)->contactsFor(
            $request->user(),
            $request->input('search') ?? data_get($request->input('filter', []), 'search')
        );

        return $this->successResponse(
            $contacts,
            __('Chat contacts retrieved successfully')
        );
    }

    /**
     * @OA\Post(
     * path="/api/v1/ppuds/chats",
     * summary="Create or get a private conversation",
     * description="Creates a new private conversation with a specific user, or returns the existing one if it already exists.",
     * tags={"Chat"},
     * security={{"sanctum": {}}},
     *
     * @OA\RequestBody(
     * required=true,
     *
     * @OA\JsonContent(
     * required={"receiver_id"},
     *
     * @OA\Property(property="receiver_id", type="integer", example=2, description="The ID of the user you want to chat with")
     * )
     * ),
     *
     * @OA\Response(
     * response=200,
     * description="Conversation created or retrieved successfully",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Conversation created successfully"),
     * @OA\Property(property="data", type="object")
     * )
     * )
     * )
     */
    public function store(ConversationRequest $request)
    {
        $request->validated();

        $receiver = User::findOrFail($request->receiver_id);

        abort_if(auth()->id() === $receiver->id, 400, __('You cannot create a conversation with yourself.'));

        if (! app(ChatContactService::class)->canStartConversation($request->user(), $receiver)) {
            return $this->errorResponse(__('You can only create conversations with your assigned contacts.'), 422);
        }

        $conversation = auth()->user()->createConversationWith($receiver);

        $conversation->load(['participants', 'lastMessage']);

        return $this->successResponse(
            new ConversationResource($conversation),
            __('Conversation created successfully')
        );
    }

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/chats/{conversation}/messages",
     * summary="Get conversation messages",
     * description="Retrieve paginated messages for a specific conversation using UUID",
     * tags={"Chat"},
     * security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     * name="conversation",
     * in="path",
     * required=true,
     * description="Conversation UUID",
     *
     * @OA\Schema(type="string", format="uuid", example="019c7fb7-8447-7263-a53d-a88d85768f73")
     * ),
     *
     * @OA\Response(response=200, description="Messages retrieved successfully"),
     * @OA\Response(response=403, description="Forbidden - User not in conversation")
     * )
     */
    public function messages(Conversation $conversation)
    {
        $this->authorizeConversationAccess($conversation);

        $messages = QueryBuilder::for($conversation->messages())
            ->with(['sendable', 'attachment'])
            ->allowedIncludes([
                'sendable',
                'attachment',
                AllowedInclude::relationship('attachments', 'attachment'),
            ])
            ->defaultSort('-created_at')
            ->paginate(request('per_page', 25));

        return $this->successResponse(
            MessageResource::collection($messages),
            __('Messages retrieved successfully')
        );
    }

    /**
     * @OA\Post(
     * path="/api/v1/ppuds/chats/{conversation}/send",
     * summary="Send a new message",
     * description="Sends a text message to a specific conversation",
     * tags={"Chat"},
     * security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     * name="conversation",
     * in="path",
     * required=true,
     * description="Conversation UUID",
     *
     * @OA\Schema(type="string", format="uuid", example="019c7fb7-8447-7263-a53d-a88d85768f73")
     * ),
     *
     * @OA\RequestBody(
     * required=true,
     *
     * @OA\JsonContent(
     * required={"body"},
     *
     * @OA\Property(property="body", type="string", example="Hello, how are you?"),
     * @OA\Property(property="attachment", type="string", format="binary", description="Optional file attachment")
     * )
     * ),
     *
     * @OA\Response(
     * response=201,
     * description="Message sent successfully",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="data", type="object")
     * )
     * )
     * )
     */
    public function sendMessage(Request $request, Conversation $conversation)
    {
        $this->authorizeConversationAccess($conversation);

        $request->merge(['body' => trim((string) $request->input('body'))]);
        $validated = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $message = $request->user()->sendMessageTo($conversation, $validated['body']);
        $message->load(['sendable', 'attachment', 'conversation.participants.participantable']);

        broadcast(new MessageCreated($message, 'chats'))->toOthers();

        app(PpudsNotificationService::class)->chatMessageCreated($message);

        return $this->successResponse(
            new MessageResource($message),
            __('Message sent successfully'),
            201
        );
    }

    /**
     * @OA\Patch(
     * path="/api/v1/ppuds/chats/{conversation}/read",
     * summary="Mark conversation as read",
     * description="Sets all unread messages in this conversation to read for the current user",
     * tags={"Chat"},
     * security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     * name="conversation",
     * in="path",
     * required=true,
     * description="Conversation UUID",
     *
     * @OA\Schema(type="string", format="uuid", example="019c7fb7-8447-7263-a53d-a88d85768f73")
     * ),
     *
     * @OA\Response(
     * response=200,
     * description="Conversation marked as read",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Marked as read")
     * )
     * )
     * )
     */
    public function markAsRead(Conversation $conversation)
    {
        $this->authorizeConversationAccess($conversation);

        $conversation->markAsRead(auth()->user());

        return $this->successResponse(null, __('Marked as read'));
    }

    private function authorizeConversationAccess(Conversation $conversation): void
    {
        abort_unless(auth()->user()?->belongsToConversation($conversation), 403, __('You do not have access to this conversation.'));
    }
}
