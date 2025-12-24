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
                    'prefix' => 'branch',
                ], function () {
                    Route::group(
                        [
                            'prefix' => 'appointment',
                            'as' => '',
                            'namespace' => 'Modules\Branch\Livewire\Pages\Branch',
                        ],
                        function () {
                            Route::get('/', 'Index')->name('branches.index');
                        }
                    );
                });
            });
        }
    );
});
