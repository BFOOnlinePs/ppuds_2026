<?php

use Illuminate\Support\Facades\Route;
use Modules\Kitchen\Http\Controllers\KitchenController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('kitchens', KitchenController::class)->names('kitchen');
});
