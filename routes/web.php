<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Modules\Core\Entities\User;
use Wirechat\Wirechat\Facades\Wirechat;
use Wirechat\Wirechat\Livewire\Chat\Chat;
use Wirechat\Wirechat\Livewire\Chats\Chats;

// });

// Route::middleware([
//     'auth:sanctum',
//     config('jetstream.auth_session'),
//     'verified',
// ])->group(function () {
//     Route::get('/dashboard', function () {
//         return view('dashboard');
//     })->name('dashboard');
// });

Route::view('/privacy-policy', 'privacy')->name('privacy.policy');

Route::get('/test-realtime', function () {
    // نضع هنا نفس اسم القناة التي نجحت في الاشتراك بها في Postman
    $channelName = 'flutter.chats.conversation.019c9906-7928-7009-94f8-487cb30f176f';

    // إطلاق حدث وهمي (Anonymous Event) للبث الفوري
    broadcast(new class($channelName) implements ShouldBroadcastNow {
        public $channel;

        public function __construct($channel) {
            $this->channel = $channel;
        }

        public function broadcastOn() {
            // نستخدم Channel عادية هنا لأنك اشتركت بدون كلمة private- في Postman
            return new Channel($this->channel);
        }

        public function broadcastAs() {
            return 'MessageSent'; // اسم الحدث
        }

        public function broadcastWith() {
            return [
                'sender' => 'Laravel Server',
                'text' => 'مرحباً! هذا اختبار حي وناجح للـ WebSockets 🚀',
                'time' => now()->toTimeString()
            ];
        }
    });

    return "تم إرسال الرسالة إلى Postman بنجاح!";
});
