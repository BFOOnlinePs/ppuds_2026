<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\Api\V1\Auth\LoginController;
use Modules\Core\Http\Controllers\Api\V1\Auth\RegisterController;
use Modules\Reels\Http\Controllers\Api\V1\ReelsController;

Route::prefix('v1')->as('api.v1.')->group(function () {

    Route::middleware(['api.localize'])
        ->prefix('reels')
        ->as('reel.')
        ->group(function () {
            Route::controller(ReelsController::class)->prefix('reels')->as('reel.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::get('/{reel}', 'show')->name('show');
                Route::delete('/{reel}', 'destroy')->name('destroy');
            });
        });

    Route::middleware(['auth:sanctum', 'api.localize'])
        ->prefix('reels')
        ->as('reel.')
        ->group(function () {
//            Route::controller(OrderController::class)->prefix('orders')->as('order.')->group(function () {
//                Route::get('/', 'index')->name('index');
//                Route::get('/{order}', 'show')->name('show');
//                Route::post('/', 'store')->name('store');
//            });
        });
});
