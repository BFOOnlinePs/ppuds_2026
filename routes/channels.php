<?php

use Illuminate\Support\Facades\Broadcast;
use Wirechat\Wirechat\Models\Conversation;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Broadcast::channel("flutter.chats.conversation.{conversationId}", function ($user, $conversationId) {
//     if (! $user) {
//         return false; 
//     }

//     // 2. البحث عن المحادثة
//     $conversation = Conversation::find($conversationId);

//     // 3. التحقق من وجود المحادثة وصلاحية المستخدم
//     return $conversation && $user->belongsToConversation($conversation);
// });

// Broadcast::channel('chats.conversation.{conversationId}', function ($user, $conversationId) {
//     \Illuminate\Support\Facades\Log::info('--- بداية فحص القناة ---');
//     \Illuminate\Support\Facades\Log::info('هل يوجد مستخدم؟ ' . ($user ? 'نعم، رقمه: ' . $user->id : 'لا، المستخدم Null! (المشكلة في الـ Token أو الـ Guard)'));
    
//     $conversation = \Wirechat\Wirechat\Models\Conversation::find($conversationId);
//     \Illuminate\Support\Facades\Log::info('هل وجد المحادثة؟ ' . ($conversation ? 'نعم' : 'لا، المحادثة Null! (المشكلة في الـ UUID أو رقم المحادثة خطأ)'));

//     $belongs = $user->belongsToConversation($conversation);
//     \Illuminate\Support\Facades\Log::info('هل المستخدم مضاف داخل المحادثة فعلياً؟ ' . ($belongs ? 'نعم' : 'لا (هنا المشكلة!)'));
//     // سنسمح بالاتصال إجبارياً لنرى هل سيتغير خطأ 403 في البوستمان
//     return true; 
// });
