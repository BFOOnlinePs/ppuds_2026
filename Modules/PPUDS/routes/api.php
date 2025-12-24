<?php

use Illuminate\Support\Facades\Route;
use Modules\PPUDS\Http\Controllers\PPUDSController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('ppuds', PPUDSController::class)->names('ppuds');
});
