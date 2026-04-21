<?php

namespace Modules\PPUDS\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Transformers\V1\UserResource;
use Spatie\QueryBuilder\AllowedSort;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $authId = auth()->id();

        return [
            'id' => $this->id,
            'type' => $this->type,

            'name' => $this->name ?? ($this->type == 'private' ? $this->getRecipientName() : null),

            'partner' => $this->whenLoaded('partners', function () use ($authId) {

                $user = $this->participants->firstWhere('participantable_id', '!=', $authId)?->participantable;

                return $user ? new UserResource($user) : null;

            }),

            'unread_count' => $this->getUnreadCount($authId),

            'last_active_at' => $this->whenLoaded('participants', fn () => $this->participants->firstWhere('participantable_id', $authId)?->last_active_at
            ),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'last_message' => new MessageResource($this->whenLoaded('lastMessage')),
            'participants' => $this->whenLoaded('participants'),
            'group_details' => $this->when($this->type === 'group', $this->group),
        ];
    }

    private function getPartnerName(int $authId)
    {
        return $this->whenLoaded('participants', fn () => $this->participants->firstWhere('participantable_id', '!=', $authId)?->participantable?->name
        );
    }

    private function getUnreadCount(int $authId): int
    {
        return (int) $this->messages()
            ->where('sendable_id', '!=', $authId)
            ->where('created_at', '>', function ($query) use ($authId) {
                $query->select('conversation_read_at')
                    ->from('wirechat_participants')
                    ->where('conversation_id', $this->id)
                    ->where('participantable_id', $authId)
                    ->limit(1);
            })->count();
    }

    public static function allowedIncludes(): array
    {
        return ['lastMessage', 'participants', 'participants.participantable', 'group', 'messages', 'partner'];
    }

    public static function allowedSorts(): array
    {
        return [AllowedSort::field('updated_at'), AllowedSort::field('created_at')];
    }
}
