<?php

use Illuminate\Support\Facades\Route;
use Modules\Reels\Http\Controllers\ReelsController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('reels', ReelsController::class)->names('reels');
});
