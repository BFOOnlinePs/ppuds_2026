<?php

use Illuminate\Support\Facades\Broadcast;
use Wirechat\Wirechat\Models\Conversation;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel("flutter.chats.conversation.{conversationId}", function ($user, $conversationId) {
    if (! $user) {
        return false; 
    }

    // 2. البحث عن المحادثة
    $conversation = Conversation::find($conversationId);

    // 3. التحقق من وجود المحادثة وصلاحية المستخدم
    return $conversation && $user->belongsToConversation($conversation);
});
