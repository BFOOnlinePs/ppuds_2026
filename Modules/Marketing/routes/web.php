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
                    'prefix' => 'marketing',
                    'as' => '',
                    'namespace' => 'Modules\Marketing\Livewire\Pages\LoyaltyRules',
                ], function () {
                    Route::group([
                        'prefix' => 'loyalty-rules',
                        'as' => '',
                    ], function () {
                        Route::get('/', Index::class)->name('loyalty-rules.index')->can('Loyalty Rules View List');
                    });
                });

                Route::group([
                    'prefix' => 'marketing',
                    'as' => '',
                    'namespace' => 'Modules\Marketing\Livewire\Pages\LoyaltyTiers',
                ], function () {
                    Route::group([
                        'prefix' => 'loyalty-tiers',
                        'as' => '',
                    ], function () {
                        Route::get('/', Index::class)->name('loyalty-tiers.index')->can('Loyalty Tiers View List');
                    });
                });
            });
        }
    );
});
