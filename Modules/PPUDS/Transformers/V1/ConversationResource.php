<?php

namespace Modules\PPUDS\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\AllowedSort;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $participant = $this->participants->where('participantable_id', auth()->id())->first();

        $unreadCount = $this->messages()
            ->where('created_at', '>', function($query) {
                $query->select('conversation_read_at')
                    ->from('wirechat_participants')
                    ->where('conversation_id', $this->id)
                    ->where('participantable_id', auth()->id())
                    ->limit(1);
            })
            ->where('sendable_id', '!=', auth()->id())
            ->count();

        return [
            'id'                => $this->id,
            'type'              => $this->type,
            'name'              => $this->name ?? ($this->type == 'private' ? $this->getRecipientName() : null),
            'unread_count'      => (int) $unreadCount,
            'last_active_at'    => $participant?->last_active_at,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,

            'last_message'      => new MessageResource($this->whenLoaded('lastMessage')),
            'participants'      => $this->whenLoaded('participants'),
            'group_details'     => $this->when($this->type == 'group', $this->group),
        ];
    }

    public static function allowedIncludes(): array
    {
        return ['lastMessage', AllowedInclude::relationship('participants', 'participants.participantable'), 'group', 'messages'];
    }

    public static function allowedSorts(): array
    {
        return [AllowedSort::field('updated_at'), AllowedSort::field('created_at')];
    }
}
