<?php

namespace Modules\PPUDS\Transformers\V1;

use BackedEnum;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Entities\User;
use Modules\Core\Transformers\V1\UserResource;

class MessageResource extends JsonResource
{
    public function toArray($request): array
    {
        $auth = $request->user();
        $isOwnedByMe = $auth
            && $this->sendable_id == $auth->getKey()
            && $this->sendable_type === $auth->getMorphClass();

        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'body' => $this->body,
            'type' => $this->type instanceof BackedEnum ? $this->type->value : $this->type,
            'sender' => [
                'id' => $this->sendable_id,
                'type' => $this->sendable_type,
            ],
            'sender_user' => $this->whenLoaded('sendable', fn () => $this->sendable instanceof User ? new UserResource($this->sendable) : null),
            'reply_to_id' => $this->reply_id,
            'is_owned_by_me' => $isOwnedByMe,
            'created_at' => $this->created_at,
            'attachment' => $this->whenLoaded('attachment'),
            'attachments' => $this->whenLoaded('attachment', fn () => $this->attachment ? [$this->attachment] : []),
        ];
    }
}
