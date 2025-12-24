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
                    'prefix' => 'clinic',
                ], function () {
                    Route::group(
                        [
                            'prefix' => 'appointment',
                            'as' => '',
                            'namespace' => 'Modules\Clinic\Livewire\Pages\Appointment',
                        ],
                        function () {
                            Route::get('/', 'Index')->name('appointments.index');
                        }
                    );
                    Route::group(
                        [
                            'prefix' => 'room',
                            'as' => '',
                            'namespace' => 'Modules\Clinic\Livewire\Pages\Room',
                        ],
                        function () {
                            Route::get('/', 'Index')->name('rooms.index')->can('Room View List');
                        }
                    );
                    Route::group(
                        [
                            'prefix' => 'disease',
                            'as' => '',
                            'namespace' => 'Modules\Clinic\Livewire\Pages\Disease',
                        ],
                        function () {
                            Route::get('/', 'Index')->name('diseases.index')->can('Disease View List');
                        }
                    );
                    Route::group(
                        [
                            'prefix' => 'program',
                            'as' => 'program.',
                            'namespace' => 'Modules\Clinic\Livewire\Pages\Program',
                        ],
                        function () {
                            Route::group(
                                [
                                    'prefix' => 'category',
                                    'as' => '',
                                    'namespace' => 'Category',
                                ],
                                function () {
                                    Route::get('/', 'Index')->name('categories.index')->can('Program Category View List');
                                }
                            );
                            Route::group(
                                [
                                    'prefix' => 'type-of-meal',
                                    'as' => '',
                                    'namespace' => 'TypesOfMeals',
                                ],
                                function () {
                                    Route::get('/', 'Index')->name('types-of-meals.index')->can('Program Type Of Meal View List');
                                }
                            );
                            Route::group(
                                [
                                    'prefix' => 'instruction',
                                    'as' => '',
                                    'namespace' => 'Instructions',
                                ],
                                function () {
                                    Route::get('/', 'Index')->name('instructions.index')->can('Program Instruction View List');
                                }
                            );
                            Route::group(
                                [
                                    'prefix' => 'program',
                                    'as' => '',
                                    'namespace' => 'Program',
                                ],
                                function () {
                                    Route::get('/', 'Index')->name('programs.index')->can('Program View List');
                                    Route::get('/{program}/details', 'Details')->name('details.index')->can('Program Details View List');
                                }
                            );
                            Route::group(
                                [
                                    'prefix' => 'customer-program',
                                    'as' => '',
                                    'namespace' => 'CustomerProgram',
                                ],
                                function () {
                                    Route::get('/', 'Index')->name('customer-programs.index')->can('Customer Program View List');
                                    Route::get('/{customerProgram}/details', 'Details')->name('customer-programs.details')->can('Customer Program View Details');
                                }
                            );
                        }

                    );
                    Route::group(
                        [
                            'prefix' => 'food',
                            'as' => 'food.',
                            'namespace' => 'Modules\Clinic\Livewire\Pages\Food',
                        ],
                        function () {
                            Route::group(
                                [
                                    'prefix' => 'item',
                                    'as' => '',
                                    'namespace' => 'Item',
                                ],
                                function () {
                                    Route::get('/', 'Index')->name('items.index')->can('Food Item View List');
                                }
                            );
                            Route::group(
                                [
                                    'prefix' => 'category',
                                    'as' => '',
                                    'namespace' => 'Category',
                                ],
                                function () {
                                    Route::get('/', 'Index')->name('categories.index')->can('Food Category View List');
                                }
                            );
                        }
                    );
                    Route::group(
                        [
                            'prefix' => 'survey',
                            'as' => '',
                            'namespace' => 'Modules\Clinic\Livewire\Pages\Survey',
                        ],
                        function () {
                            Route::get('/', 'Index')->name('surveys.index')->can('Survey View List');
                        }
                    );
                });
            });
        }
    );
});
