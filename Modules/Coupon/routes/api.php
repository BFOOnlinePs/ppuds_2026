<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\Api\V1\Auth\LoginController;
use Modules\Core\Http\Controllers\Api\V1\Auth\RegisterController;
use Modules\Coupon\Http\Controllers\Api\V1\CouponsController;
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
| 1. Authentication: For guests (login, registration) and authenticated users (logout).
| 2. Public Routes: Accessible by anyone (e.g., viewing products, categories).
| 3. Protected Routes: Require authentication (e.g., creating an order, viewing profile).
|
*/

Route::prefix('v1')->as('api.v1.')->group(function () {

    Route::middleware(['api.localize'])
        ->prefix('coupon')
        ->as('coupon.')
        ->group(function () {

        });

    Route::middleware(['auth:sanctum', 'api.localize'])
        ->prefix('coupon')
        ->as('coupon.')
        ->group(function () {
            Route::controller(CouponsController::class)->prefix('coupons')->as('coupon.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/{coupon}', 'show')->name('show');
                Route::post('/apply', 'apply')->name('apply');
            });
        });
});
