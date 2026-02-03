<?php

use Illuminate\Support\Facades\Route;
use Modules\PPUDS\Http\Controllers\Api\V1\CompanyController;
use Modules\PPUDS\Http\Controllers\Api\V1\CompanyDepartmentController;


Route::prefix('v1')->as('api.v1.')->group(function () {
    Route::middleware(['auth:sanctum', 'api.localize'])->group(function () {

        Route::prefix('ppuds')->as('ppuds.')->group(function () {

            Route::controller(CompanyController::class)
                ->prefix('companies')
                ->as('companies.')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/', 'store')->name('store');
                    Route::get('/{company}', 'show')->name('show');
                });

            Route::controller(CompanyDepartmentController::class)
                ->prefix('company-departments')
                ->as('company-departments.')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/', 'store')->name('store');
                    Route::get('/{department}', 'show')->name('show');
                });

        });

    });

});
