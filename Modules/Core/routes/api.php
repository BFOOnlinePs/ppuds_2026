<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\Api\V1\ActivityLogController;
use Modules\Core\Http\Controllers\Api\V1\Auth\LoginController;
use Modules\Core\Http\Controllers\Api\V1\Auth\RegisterController;
use Modules\Core\Http\Controllers\Api\V1\CurrencyController;
use Modules\Core\Http\Controllers\Api\V1\SyncController;
use Modules\Core\Http\Controllers\Api\V1\UserController;
use Modules\Core\Http\Controllers\Api\V1\ConfigController;

Route::prefix('v1')->as('api.v1.')->group(function () {

    Route::prefix('auth')->as('auth.')->group(function () {
        Route::post('login', [LoginController::class, 'login'])->name('login');
        Route::post('register', [RegisterController::class, 'register'])->name('register');
    });

    Route::controller(ConfigController::class)->prefix('config')->as('config.')->group(function () {
        Route::get('/', 'index')->name('index');
    });

    Route::middleware('auth:sanctum')->group(function () {

        Route::prefix('auth')->as('auth.')->group(function () {
            Route::post('logout', [LoginController::class, 'logout'])->name('logout');
        });

        Route::controller(UserController::class)->prefix('users')->as('user.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/{user}', 'show')->name('show');
            Route::patch('/{user}', 'update')->name('update');
            Route::delete('/{user}', 'destroy')->name('delete');
        });

        Route::controller(ActivityLogController::class)->prefix('activity-logs')->as('activity-logs.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/{activity}', 'show')->name('show');
        });

        Route::controller(CurrencyController::class)->prefix('currencies')->as('currencies.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/{currency}', 'show')->name('show');
        });

        Route::controller(SyncController::class)->prefix('sync')->as('sync.')->group(function () {
            Route::get('/users', 'syncUsers')->name('users');
            Route::get('/roles', 'syncRoles')->name('roles');
        });
    });

});
