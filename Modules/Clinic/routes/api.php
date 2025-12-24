<?php

use Illuminate\Support\Facades\Route;
use Modules\Clinic\Http\Controllers\ClinicController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('clinics', ClinicController::class)->names('clinic');
});
