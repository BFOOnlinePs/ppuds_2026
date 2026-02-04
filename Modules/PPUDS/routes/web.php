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
                    'namespace' => 'Modules\PPUDS\Livewire\Pages\Student',
                ], function () {
                    Route::get('/students', Index::class)->name('students.index')->can('Student View List');
//                    Route::get('/students/add', Add::class)->name('students.add')->can('Student Create');
//                    Route::get('/students/{user}/edit', Edit::class)->name('students.edit')->can('Student Update');
                });

                Route::group([
                    'prefix' => '',
                    'as' => '',
                    'namespace' => 'Modules\PPUDS\Livewire\Pages\CompanyCategory',
                ], function () {
                    Route::get('/company-category', Index::class)->name('company-category.index')->can('Company Category View List');
                });

                Route::group([
                    'prefix' => '',
                    'as' => '',
                    'namespace' => 'Modules\PPUDS\Livewire\Pages\CompanyDepartment',
                ], function () {
                    Route::get('/company-department', Index::class)->name('company-department.index')->can('Company Department View List');
                });

                Route::group([
                    'prefix' => '',
                    'as' => '',
                    'namespace' => 'Modules\PPUDS\Livewire\Pages\Company',
                ], function () {
                    Route::get('/companies', Index::class)->name('companies.index')->can('Company View List');
                    Route::get('/companies/add', Add::class)->name('companies.add')->can('Company Create');
                    Route::get('/companies/{company}/edit', Edit::class)->name('companies.edit')->can('Company Update');
                });

                Route::group([
                    'prefix' => 'majors',
                    'as' => 'majors.',
                    'namespace' => 'Modules\PPUDS\Livewire\Pages\Major',
                ], function () {
                    Route::get('/majors', Index::class)->name('index')->can('Student View List');
                });

                Route::group([
                    'prefix' => 'courses',
                    'as' => 'courses.',
                    'namespace' => 'Modules\PPUDS\Livewire\Pages\Course',
                ], function () {
                    Route::get('/courses', Index::class)->name('index')->can('Course View List');
                });
            });
        }
    );
});
