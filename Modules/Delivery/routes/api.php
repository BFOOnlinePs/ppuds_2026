<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\Api\V1\Auth\LoginController;
use Modules\Core\Http\Controllers\Api\V1\Auth\RegisterController;
use Modules\Delivery\Http\Controllers\Api\V1\CustomerAddressesController;
use Modules\Delivery\Http\Controllers\Api\V1\DeliveryCalculationController;
use Modules\Delivery\Http\Controllers\Api\V1\DeliveryPricingController;
use Modules\Delivery\Http\Controllers\Api\V1\DeliveryZoneController;

/*
|--------------------------------------------------------------------------
| API Routes V1
|--------------------------------------------------------------------------
|
| API v1 routes are grouped here.
| Routes are divided into three main sections:
| 1. Authentication: For guests (login, register) and authenticated users (logout).
| 2. Public Routes: Accessible by anyone (e.g., viewing products, categories).
| 3. Protected Routes: Require authentication (e.g., creating an order, viewing profile).
|
*/

Route::prefix('v1')->as('api.v1.')->group(function () {

    Route::middleware(['api.localize'])
        ->prefix('delivery')
        ->as('delivery.')
        ->group(function () {

            Route::controller(DeliveryZoneController::class)->prefix('zones')->as('zone.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/{zone}', 'show')->name('show');
            });

            Route::controller(DeliveryCalculationController::class)->as('calculation.')->group(function () {
                Route::post('/calculate-fee', '__invoke')->name('calculate-fee');
            });

        });

    Route::middleware(['auth:sanctum', 'api.localize'])
        ->prefix('delivery')
        ->as('delivery.')
        ->group(function () {

            Route::controller(DeliveryPricingController::class)->prefix('pricings')->as('pricing.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/{pricing}', 'show')->name('show');
            });

            Route::controller(CustomerAddressesController::class)->prefix('customer-addresses')->as('customer-addresses.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/{customerAddresses}', 'show')->name('show');
                Route::post('/', 'store')->name('store');
                Route::put('/{customerAddresses}', 'update')->name('update');
                Route::delete('/{id}', 'destroy')->name('destroy');
            });

//            Route::controller(DeliveryCalculationController::class)->as('calculation.')->group(function () {
//                Route::post('/calculate-fee', '__invoke')->name('calculate-fee');
//            });

        });

});
