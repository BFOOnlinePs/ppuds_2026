<?php

namespace Modules\PPUDS\Transformers\V1;

use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Entities\User;
use Modules\Core\Transformers\V1\UserResource;
use Spatie\QueryBuilder\AllowedSort;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $auth = $request->user();
        $participant = $auth ? $this->participant($auth) : null;
        $partner = $this->partner($auth);

        return [
            'id' => $this->id,
            'type' => $this->type instanceof BackedEnum ? $this->type->value : $this->type,

            'name' => $this->conversationName($partner),

            'partner' => $this->when($partner instanceof User, fn () => new UserResource($partner)),

            'unread_count' => $auth ? $this->getUnreadCountFor($auth) : 0,

            'last_active_at' => $participant?->last_active_at,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'last_message' => new MessageResource($this->whenLoaded('lastMessage')),
            'participants' => $this->whenLoaded('participants'),
            'group_details' => $this->when($this->isGroup(), $this->group),
        ];
    }

    private function partner(?User $auth): ?User
    {
        if (! $auth) {
            return null;
        }

        $partner = $this->peerParticipant($auth)?->participantable;

        return $partner instanceof User ? $partner : null;
    }

    private function conversationName(?User $partner): ?string
    {
        if ($this->isGroup()) {
            return $this->group?->name;
        }

        return $partner?->name;
    }

    public static function allowedIncludes(): array
    {
        return ['lastMessage', 'lastMessage.attachment', 'lastMessage.sendable', 'participants', 'participants.participantable', 'group', 'messages'];
    }

    public static function allowedSorts(): array
    {
        return [AllowedSort::field('updated_at'), AllowedSort::field('created_at')];
    }
}
