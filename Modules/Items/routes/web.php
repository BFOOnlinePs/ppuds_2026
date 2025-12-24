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
                    'prefix' => '',
                    'as' => '',
                    'namespace' => 'Modules\Items\Livewire\Pages\Category',
                ], function () {
                    Route::get('/categories', Index::class)->name('categories.index')->can('Category View List');
                    Route::get('/categories/add', Add::class)->name('categories.add')->can('Category Create');
                    Route::get('/categories/{category}/edit', Edit::class)->name('categories.edit')->can('Category Update');
                });

                Route::group([
                    'prefix' => '',
                    'as' => '',
                    'namespace' => 'Modules\Items\Livewire\Pages\Product',
                ], function () {
                    Route::get('/products', Index::class)->name('products.index')->can('Product View List');
                    Route::get('/products/add', Add::class)->name('products.add')->can('Product Create');
                    Route::get('/products/{product}/edit' , Edit::class)->name('products.edit')->can('Product Update');
                    Route::get('/products/{product}/branch-pricings' , BranchPricings::class)->name('products.branch-pricings')->can('Product Branch Pricings');
                });

                Route::group([
                    'prefix' => '',
                    'as' => '',
                    'namespace' => 'Modules\Items\Livewire\Pages\Attribute',
                ], function () {
                    Route::get('/attributes', Index::class)->name('attributes.index')->can('Attribute View List');
                    Route::get('/attributes/add', Add::class)->name('attributes.add')->can('Attribute Create');
                    Route::get('/attributes/{attribute}/edit' , Edit::class)->name('attributes.edit')->can('Attribute Update');
                });

                Route::group([
                    'prefix' => '',
                    'as' => '',
                    'namespace' => 'Modules\Items\Livewire\Pages\Tag',
                ], function () {
                    Route::get('/tags', Index::class)->name('tags.index')->can('Tag View List');
                });

                Route::group([
                    'prefix' => '',
                    'as' => '',
                    'namespace' => 'Modules\Items\Livewire\Pages\Label',
                ], function () {
                    Route::get('/labels', Index::class)->name('labels.index')->can('Label View List');
                });

                Route::group([
                    'prefix' => '',
                    'as' => '',
                    'namespace' => 'Modules\Items\Livewire\Pages\Brand',
                ], function () {
                    Route::get('/brand', Index::class)->name('brands.index')->can('Brand View List');
                });

                Route::group([
                    'prefix' => '',
                    'as' => '',
                    'namespace' => 'Modules\Items\Livewire\Pages\Offer',
                ], function () {
                    Route::get('/offers', Index::class)->name('offers.index')->can('Offer View List');
                    Route::get('/offers/add', Add::class)->name('offers.add')->can('Offer Create');
                    Route::get('/offers/{offer}/edit' , Edit::class)->name('offers.edit')->can('Offer Update');
                });

                Route::group([
                    'prefix' => '',
                    'as' => '',
                    'namespace' => 'Modules\Items\Livewire\Pages\Addon',
                ], function () {
                    Route::get('/addons', Index::class)->name('addons.index')->can('Addon View List');
                    Route::get('/addons/add', Add::class)->name('addons.add')->can('Addon Create');
                    Route::get('/addons/{addon}/edit' , Edit::class)->name('addons.edit')->can('Addon Update');
                });

                Route::group([
                    'prefix' => '',
                    'as' => '',
                    'namespace' => 'Modules\Items\Livewire\Pages\Order',
                ], function () {
                    Route::get('/orders', Index::class)->name('orders.index')->can('Order View List');
                    Route::get('/orders/add', Add::class)->name('orders.add')->can('Order Create');
                    Route::get('/orders/{order}/edit' , Edit::class)->name('orders.edit')->can('Order Update');
                });
            });
        }
    );
});
