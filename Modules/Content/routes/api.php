<?php

use Illuminate\Support\Facades\Route;
use Modules\Content\Http\Controllers\Api\V1\BannersController;
use Modules\Content\Http\Controllers\Api\V1\FaqCategoriesController;
use Modules\Content\Http\Controllers\Api\V1\FaqsController;
use Modules\Content\Http\Controllers\Api\V1\PagesController;

Route::prefix('v1')->as('api.v1.')->group(function () {

    /**
     * ==========================================
     * Routes عامة (لا تتطلب تسجيل دخول)
     * ==========================================
     * هذه الروابط متاحة للجميع وتستخدم middleware
     * 'api.localize' فقط.
     */
    Route::middleware(['api.localize'])
        ->prefix('content') // تم دمج البريفكس هنا مرة واحدة
        ->as('content.')
        ->group(function () {

            // Banners Routes
            Route::prefix('banners')->as('banners.')->group(function () {
                Route::get('/', [BannersController::class, 'index'])->name('index');
                Route::get('/{banner}', [BannersController::class, 'show'])->name('show');
            });

            // Pages Routes
            Route::prefix('pages')->as('pages.')->group(function () {
                Route::get('/', [PagesController::class, 'index'])->name('index');
                Route::get('/{slug}', [PagesController::class, 'show'])->name('show');
            });

            // FAQ Categories Routes
            Route::prefix('faq-categories')->as('faq-categories.')->group(function () {
                Route::get('/', [FaqCategoriesController::class, 'index'])->name('index');
                Route::get('/{slug}', [FaqCategoriesController::class, 'show'])->name('show');
            });

            // FAQs Routes (Direct access if needed)
            Route::prefix('faqs')->as('faqs.')->group(function () {
                Route::get('/', [FaqsController::class, 'index'])->name('index');
                Route::get('/{id}', [FaqsController::class, 'show'])->name('show');
            });
        });

    /**
     * ==========================================
     * Routes محمية (تتطلب تسجيل دخول)
     * ==========================================
     * أي روابط تتطلب 'auth:sanctum' توضع هنا
     */
    Route::middleware(['auth:sanctum', 'api.localize'])->group(function () {

        // مثال:
        // Route::get('/my-profile', [ProfileController::class, 'show']);

    });

});
