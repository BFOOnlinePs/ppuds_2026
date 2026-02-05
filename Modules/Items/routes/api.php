<?php

use Illuminate\Support\Facades\Route;
use Modules\Items\Http\Controllers\Api\V1\Api\V1\AddonController;
use Modules\Items\Http\Controllers\Api\V1\Api\V1\AttributeController;
use Modules\Items\Http\Controllers\Api\V1\Api\V1\CategoryController;
use Modules\Items\Http\Controllers\Api\V1\Api\V1\OfferController;
use Modules\Items\Http\Controllers\Api\V1\Api\V1\OrderController;
use Modules\Items\Http\Controllers\Api\V1\Api\V1\ProductController;
use Modules\Items\Http\Controllers\Api\V1\Api\V1\SyncController;

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
        ->prefix('items')
        ->as('item.')
        ->group(function () {

            // Categories
            Route::controller(CategoryController::class)->prefix('categories')->as('category.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/{category}', 'show')->name('show');
            });

            // Products
            Route::controller(ProductController::class)->prefix('products')->as('product.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/{product}', 'show')->name('show');
                Route::get('/{product}/attributes', 'getAttributes')->name('getAttributes');
                Route::get('/{product}/addons', 'getAddons')->name('getAddons');
            });

            // Attributes
            Route::controller(AttributeController::class)->prefix('attributes')->as('attribute.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/{attribute}', 'show')->name('show');
            });

            // Offers
            Route::controller(OfferController::class)->prefix('offers')->as('offer.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/{offer}', 'show')->name('show');
            });

            // Addons
            Route::controller(AddonController::class)->prefix('addons')->as('addon.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/{addon}', 'show')->name('show');
            });

        });

    Route::middleware(['auth:sanctum', 'api.localize'])
        ->prefix('items')
        ->as('item.')
        ->group(function () {

            Route::controller(OrderController::class)->prefix('orders')->as('order.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/{order}', 'show')->name('show');
                Route::post('/', 'store')->name('store');
                Route::patch('/{order}/status', 'updateOrderStatus')->name('updateOrderStatus');
            });

            Route::controller(SyncController::class)->prefix('sync')->as('sync.')->group(function () {
                Route::get('/products', 'syncProducts')->name('products');
                Route::get('/categories', 'syncCategories')->name('categories');
                Route::get('/addons', 'syncAddons')->name('addons');
                Route::get('/addonsOptions', 'syncAddonsOptions')->name('addonsOptions');
                Route::get('/offers', 'syncOffers')->name('offers');
                Route::get('/orders', 'syncOrders')->name('orders');
            });
        });
});
