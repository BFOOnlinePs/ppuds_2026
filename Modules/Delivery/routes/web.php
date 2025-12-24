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
                    'prefix' => 'delivery',
                    'as' => '',
                    'namespace' => '',
                ], function () {
                    Route::group([
                        'prefix' => 'delivery-pricing',
                        'as' => '',
                        'namespace' => 'Modules\Delivery\Livewire\Pages\DeliveryPricing',
                    ], function () {
                        Route::get('/', Index::class)->name('delivery-pricing.index')->can('Delivery Pricing View List');
                    });

                    Route::group([
                        'prefix' => 'delivery-zones',
                        'as' => '',
                        'namespace' => 'Modules\Delivery\Livewire\Pages\DeliveryZone',
                    ], function () {
                        Route::get('/', Index::class)->name('delivery-zone.index')->can('Delivery Zone View List');
                        Route::get('/add', Add::class)->name('delivery-zone.add')->can('Delivery Zone Create');
                        Route::get('/{deliveryZone}/edit', Edit::class)->name('delivery-zone.edit')->can('Delivery Zone Update');
                    });
                });
            });
        }
    );
});
