<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::group(
        [
            'prefix' => \Mcamara\LaravelLocalization\Facades\LaravelLocalization::setLocale(),
            'middleware' => ['localeSessionRedirect', 'localizationRedirect', 'localeViewPath']
        ],
        function () {

            Livewire::setUpdateRoute(function ($handle) {
                return Route::post('/livewire/update', $handle);
            });

            Route::group([
                'prefix' => 'admin',
                'as' => '',
            ], function () {
                Route::group([
                    'prefix' => 'coupons',
                    'as' => 'coupons.',
                    'namespace' => 'Modules\Coupon\Livewire\Pages\Coupon',
                ], function () {
                    Route::get('/', 'Index')->name('index');
                    Route::get('/create', 'Add')->name('add');
                    Route::get('/{coupon}/edit', 'Edit')->name('edit');
                });
            });
        }
    );
});
