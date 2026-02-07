<?php

 use Illuminate\Support\Facades\Route;
use Modules\Core\Entities\User;
use Wirechat\Wirechat\Facades\Wirechat;
use Wirechat\Wirechat\Livewire\Chats\Chats;

Route::middleware(['web', 'auth'])->get('/start-chat/{user}', function (User $user) {

    // 1. المستخدم الحالي (الذي يريد بدء المحادثة)
    $me = auth()->user();

    // 2. الطريقة الصحيحة لإنشاء المحادثة من التوثيق الرسمي
    // هذه الدالة ستنشئ محادثة جديدة أو تعيد المحادثة القديمة إذا كانت موجودة
    $conversation = $me->createConversationWith($user);

    // 3. توجيه المستخدم لصفحة الشات
    return redirect()->to('/chats');

})->name('chat.start');//     return view('welcome');
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
