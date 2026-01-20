<?php

use Illuminate\Support\Facades\Route;
use Modules\Kitchen\Http\Controllers\KitchenController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('kitchens', KitchenController::class)->names('kitchen');
});
