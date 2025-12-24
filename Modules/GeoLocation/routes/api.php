<?php

use Illuminate\Support\Facades\Route;
use Modules\GeoLocation\Http\Controllers\Api\V1\CityController;
use Modules\GeoLocation\Http\Controllers\Api\V1\CountryController;
use Modules\GeoLocation\Http\Controllers\Api\V1\DistrictController;
use Modules\GeoLocation\Http\Controllers\Api\V1\GovernorateController;
use Modules\Items\Http\Controllers\Api\V1\AttributeController;
use Modules\Items\Http\Controllers\Api\V1\CategoryController;
use Modules\Items\Http\Controllers\Api\V1\OfferController;
use Modules\Items\Http\Controllers\Api\V1\OrderController;
use Modules\Items\Http\Controllers\Api\V1\ProductController;

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
        ->prefix('geolocation')
        ->as('geolocation.')
        ->group(callback: function () {

            Route::controller(CityController::class)->prefix('cities')->as('city.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/{city}', 'show')->name('show');
            });

            Route::controller(CountryController::class)->prefix('countries')->as('country.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/{country}', 'show')->name('show');
            });

            Route::controller(DistrictController::class)->prefix('districts')->as('district.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/{district}', 'show')->name('show');
            });

            Route::controller(GovernorateController::class)->prefix('governorates')->as('governorate.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/{governorate}', 'show')->name('show');
            });

        });
});
