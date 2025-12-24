<?php

use Illuminate\Support\Facades\Route;
use Modules\GeoLocation\Http\Controllers\GeoLocationController;

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
                    'prefix' => 'geolocation',
                    'as' => '',
                    'namespace' => '',
                ], function () {
                    Route::group([
                        'prefix' => 'countries',
                        'as' => 'countries.',
                        'namespace' => 'Modules\GeoLocation\Livewire\Pages\Country',
                    ], function () {
                        Route::get('/', Index::class)->name('index')->can('Country View List');
                        Route::get('/add', Add::class)->name('add')->can('Country Create');
                        Route::get('/{country}/edit', Edit::class)->name('edit')->can('Country Edit');
                    });
                    Route::group([
                        'prefix' => 'governorates',
                        'as' => 'governorates.',
                        'namespace' => 'Modules\GeoLocation\Livewire\Pages\Governorate',
                    ], function () {
                        Route::get('/', Index::class)->name('index')->can('Governorate View List');
                        Route::get('/add', Add::class)->name('add')->can('Governorate Create');
                        Route::get('/{governorate}/edit', Edit::class)->name('edit')->can('Governorate Update');
                    });
                    Route::group([
                        'prefix' => 'cities',
                        'as' => 'cities.',
                        'namespace' => 'Modules\GeoLocation\Livewire\Pages\City',
                    ], function () {
                        Route::get('/', Index::class)->name('index')->can('City View List');
                        Route::get('/add', Add::class)->name('add')->can('City Create');
                        Route::get('/{city}/edit', Edit::class)->name('edit')->can('City Update');
                    });
                    Route::group([
                        'prefix' => 'districts',
                        'as' => 'districts.',
                        'namespace' => 'Modules\GeoLocation\Livewire\Pages\District',
                    ], function () {
                        Route::get('/', Index::class)->name('index')->can('District View List');
                        Route::get('/add', Add::class)->name('add')->can('District Add');
                        Route::get('/{district}/edit', Edit::class)->name('edit')->can('District Update');
                    });
                });
            });
        }
    );
});
