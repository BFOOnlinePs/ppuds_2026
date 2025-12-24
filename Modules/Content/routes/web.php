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
                    'prefix' => 'content',
                    'as' => '',
                ], function (){
                    Route::group([
                        'prefix' => 'banners',
                        'as' => '',
                        'namespace' => 'Modules\Content\Livewire\Pages\Banner',
                    ] , function () {
                        Route::get('/', 'Index')->name('banners.index');
                    });

                    Route::group([
                        'prefix' => 'pages',
                        'as' => '',
                        'namespace' => 'Modules\Content\Livewire\Pages\Page',
                    ] , function () {
                        Route::get('/', 'Index')->name('pages.index');
                        Route::get('/add', 'Add')->name('pages.add');
                        Route::get('/{slug}/edit', 'Edit')->name('pages.edit');
                    });

                    Route::group([
                        'prefix' => 'faqs',
                        'as' => '',
                        'namespace' => 'Modules\Content\Livewire\Pages\Faq',
                    ] , function () {
                        Route::get('/', 'Index')->name('faqs.index');
                        Route::get('/add', 'Add')->name('faqs.add');
                        Route::get('/{slug}/edit', 'Edit')->name('faqs.edit');
                    });
                });
            });
        }
    );
});
