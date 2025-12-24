<?php

use Illuminate\Support\Facades\Route;
use Modules\Branch\Http\Controllers\Api\V1\SyncController;
use Modules\Core\Http\Controllers\Api\V1\Auth\LoginController;
use Modules\Core\Http\Controllers\Api\V1\Auth\RegisterController;
use Modules\Branch\Http\Controllers\Api\V1\BranchController;

//Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
//});

Route::prefix('v1')->as('api.v1.')->group(function () {

    Route::middleware(['api.localize'])->group(function () {
        Route::controller(BranchController::class)->prefix('branches')->as('branch.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/{branch}', 'show')->name('show');
        });
    });

    Route::middleware(['auth:sanctum', 'api.localize'])
        ->prefix('branches')
        ->as('branch.')
        ->group(function () {
            Route::controller(SyncController::class)->prefix('sync')->as('sync.')->group(function () {
                Route::get('/branches', 'syncBranches')->name('branches');
            });
        });
});
