<?php

namespace Modules\PPUDS\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\Core\Traits\ApiResponse;
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Http\Requests\ConversationRequest;
use Modules\PPUDS\Services\PpudsNotificationService;
use Modules\PPUDS\Settings\GeneralSettings;
use Modules\PPUDS\Transformers\V1\ConversationResource;
use Modules\PPUDS\Transformers\V1\MessageResource;
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
    public function index()
    {
        $conversations = QueryBuilder::for(Conversation::class)
            ->whereHas('participants', function ($q) {
                $q->where('participantable_id', auth()->id());
            })
            ->allowedIncludes(ConversationResource::allowedIncludes())
            ->defaultSort('-updated_at')
            ->paginate(request('per_page', 15));

        return $this->successResponse(
            ConversationResource::collection($conversations),
            __('Conversations retrieved successfully')
        );
    }

    public function contacts(Request $request)
    {
        $contactTypes = $this->studentSupervisorContactTypes(auth()->user());
        $contactIds = array_keys($contactTypes);

        $contacts = User::query()
            ->whereIn('id', $contactIds)
            ->when($request->input('search') ?? data_get($request->input('filter', []), 'search'), function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->get()
            ->map(function (User $user) use ($contactTypes) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'image' => $user->getProfileImageUrlAttribute(),
                    'supervisor_types' => $contactTypes[$user->id] ?? [],
                ];
            })
            ->values();

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

        if (auth()->user()?->hasRole(UserRole::STUDENT->value) && ! $this->studentSupervisorContactIds(auth()->user())->contains($receiver->id)) {
            return $this->errorResponse(__('You can only create conversations with your assigned supervisors.'), 422);
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
        // abort_unless($conversation->hasParticipant(auth()->user()), 403);

        $messages = QueryBuilder::for($conversation->messages())
            ->allowedIncludes(['sendable', 'attachments'])
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
    public function sendMessage(Request $request, $conversationId)
    {
        $conversation = Conversation::findOrFail($conversationId);
        $request->validate(['body' => 'required|string']);

        $message = auth()->user()->sendMessageTo($conversation, $request->body);

        broadcast((new MessageCreated($message, 'chats'))->onConnection('sync'));

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
        $conversation->markAsRead(auth()->user());

        return $this->successResponse(null, __('Marked as read'));
    }

    private function studentSupervisorContactIds(User $student): Collection
    {
        return collect(array_keys($this->studentSupervisorContactTypes($student)))
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    private function studentSupervisorContactTypes(User $student): array
    {
        if (! $student->hasRole(UserRole::STUDENT->value)) {
            return [];
        }

        $contactTypes = [];

        $this->currentStudentRegistrations($student)
            ->pluck('supervisor_id')
            ->filter()
            ->each(function ($supervisorId) use (&$contactTypes) {
                $contactTypes[(int) $supervisorId][] = 'university_supervisor';
            });

        $this->companySupervisorIds($student)
            ->each(function ($supervisorId) use (&$contactTypes) {
                $contactTypes[(int) $supervisorId][] = 'company_supervisor';
            });

        unset($contactTypes[$student->id]);

        return collect($contactTypes)
            ->map(fn (array $types) => array_values(array_unique($types)))
            ->toArray();
    }

    private function currentStudentRegistrations(User $student): Collection
    {
        $settings = app(GeneralSettings::class);

        return Registration::query()
            ->where('student_id', $student->id)
            ->where('semester', $settings->semester_type?->value)
            ->where('year', $settings->year)
            ->get();
    }

    private function companySupervisorIds(User $student): Collection
    {
        $studentCompanies = StudentCompany::query()
            ->where('student_id', $student->id)
            ->whereHas('registration', function ($query) {
                $settings = app(GeneralSettings::class);

                $query->where('semester', $settings->semester_type?->value)
                    ->where('year', $settings->year);
            })
            ->get(['branch_id', 'department_id']);

        $branchDepartmentPairs = $studentCompanies
            ->filter(fn (StudentCompany $studentCompany) => $studentCompany->branch_id && $studentCompany->department_id)
            ->map(fn (StudentCompany $studentCompany) => [
                'branch_id' => $studentCompany->branch_id,
                'department_id' => $studentCompany->department_id,
            ])
            ->unique(fn (array $pair) => $pair['branch_id'].'-'.$pair['department_id'])
            ->values();

        $branchIds = $studentCompanies
            ->filter(fn (StudentCompany $studentCompany) => $studentCompany->branch_id && ! $studentCompany->department_id)
            ->pluck('branch_id')
            ->unique()
            ->values();

        $companySupervisorIds = collect();

        if ($branchDepartmentPairs->isNotEmpty()) {
            $companySupervisorIds = $companySupervisorIds->merge(
                DB::table(config('ppuds.table_prefix').'branch_department')
                    ->where(function ($query) use ($branchDepartmentPairs) {
                        $branchDepartmentPairs->each(function (array $pair) use ($query) {
                            $query->orWhere(function ($query) use ($pair) {
                                $query->where('branch_id', $pair['branch_id'])
                                    ->where('company_department_id', $pair['department_id']);
                            });
                        });
                    })
                    ->pluck('user_id')
            );
        }

        if ($branchIds->isNotEmpty()) {
            $companySupervisorIds = $companySupervisorIds->merge(
                DB::table(config('ppuds.table_prefix').'branch_department')
                    ->whereIn('branch_id', $branchIds)
                    ->pluck('user_id')
            );
        }

        return $companySupervisorIds
            ->filter()
            ->unique()
            ->values();
    }
}
