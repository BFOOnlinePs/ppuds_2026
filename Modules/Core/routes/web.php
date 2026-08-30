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

            Route::get('/', Modules\Core\Livewire\Pages\Home\Index::class)->name('dashboard');

            Route::get('/select-module', \Modules\Core\Livewire\ModuleSelector::class)->name('module-selector');

            Route::group([
                'prefix' => 'admin',
                'as' => '',
            ], function () {
                Route::group([
                    'prefix' => '',
                    'as' => '',
                    'namespace' => 'Modules\Core\Livewire\Pages\Home',
                ], function () {
                    Route::get('/', Index::class)->name('home');
                });

                Route::group([
                    'prefix' => '',
                    'as' => '',
                    'namespace' => 'Modules\Core\Livewire\Pages\Settings',
                ], function () {
                    Route::get('/settings', Index::class)->name('settings')->can('Setting View List');
                });

                Route::group([
                    'prefix' => 'profile',
                    'as' => 'profile.',
                    'namespace' => 'Modules\Core\Livewire\Pages\Profile',
                ], function () {
                    Route::get('/', Index::class)->name('index');
                });

                Route::group([
                    'prefix' => '',
                    'as' => '',
                    'namespace' => 'Modules\Core\Livewire\Pages\Roles',
                ], function () {
                    Route::get('/roles', Index::class)->name('roles.index')->can('Roles And Permissions View List');
                });

                Route::group([
                    'prefix' => 'users',
                    'as' => '',
                    'namespace' => 'Modules\Core\Livewire\Pages\Users',
                ], function () {
                    Route::get('/', Index::class)->name('users.index')->can('User View List');
                    Route::get('/add', Add::class)->name('users.add')->can('User Create');
                    Route::get('/{user}/edit', Edit::class)->name('users.edit')->can('User Update');
                });

                Route::group([
                    'prefix' => 'currencies',
                    'as' => 'currencies.',
                    'namespace' => 'Modules\Core\Livewire\Pages\Currency',
                ], function () {
                    Route::get('/', Index::class)->name('index')->can('Currency View List');
                    Route::get('/add', Add::class)->name('add')->can('Currency Create');
                    Route::get('/{currency}/edit', Edit::class)->name('edit')->can('Currency Update');
                });

                Route::group([
                    'prefix' => 'activity-logs',
                    'as' => 'activity-logs.',
                    'namespace' => 'Modules\Core\Livewire\Pages\ActivityLog',
                ], function () {
                    Route::get('/', Index::class)->name('index')->can('Activity Log View List');
                    Route::get('/auth', AuthLog::class)->name('auth')->can('Activity Log View List');
                });
            });
        }
    );
});
