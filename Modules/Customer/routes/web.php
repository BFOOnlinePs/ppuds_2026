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
                    'prefix' => 'customer',
                    'as' => '',
                    'namespace' => 'Modules\Customer\Livewire\Pages\Customer',
                ], function () {
                    Route::get('/', Index::class)->name('customers.index')->can('Customer View List');
                    Route::get('/add', Add::class)->name('customers.add')->can('Customer Create');
                    Route::get('/{customer}/edit', Edit::class)->name('customers.edit')->can('Customer Update');
                    Route::get('/{customer}/details', Details::class)->name('customers.details')->can('Customer View Details');
                });
            });
        }
    );
});
