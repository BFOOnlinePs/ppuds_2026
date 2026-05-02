<?php

namespace Modules\PPUDS\Transformers\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'conversation_id' => $this->conversation_id,
            'body'            => $this->body,
            'type'            => $this->type,
            'sender'          => [
                'id'   => $this->sendable_id,
                'type' => $this->sendable_type,
            ],
            'reply_to_id'     => $this->reply_id,
            'is_owned_by_me'  => $this->sendable_id == auth()->id(),
            'created_at'      => $this->created_at,
            'attachments'     => $this->whenLoaded('attachments'), // مرجع لجدول wirechat_attachments
        ];
    }
}
