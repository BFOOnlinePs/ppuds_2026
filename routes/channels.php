<?php

use Illuminate\Support\Facades\Broadcast;
use Wirechat\Wirechat\Helpers\MorphClassResolver;
use Wirechat\Wirechat\Models\Conversation;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('chats.conversation.{conversationId}', function ($user, string $conversationId) {
    $conversation = Conversation::query()->find($conversationId);

    return $user && $conversation && $user->belongsToConversation($conversation);
});

Broadcast::channel('chats.participant.{encodedType}.{id}', function ($user, string $encodedType, string $id) {
    $participantType = MorphClassResolver::decode($encodedType);

    return $user
        && $participantType !== false
        && $user->getMorphClass() === $participantType
        && (string) $user->getKey() === $id;
});
