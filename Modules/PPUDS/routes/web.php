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
                
                // ... (جميع الـ Routes الأخرى الخاصة بك مثل الطلاب والشركات وغيرها) ...
                Route::group([
                    'prefix' => '',
                    'as' => '',
                    'namespace' => 'Modules\PPUDS\Livewire\Pages\Student',
                ], function () {
                    Route::get('/students', Index::class)->name('students.index')->can('Student View List');
                    Route::get('/students/{user}/details', Details\Details::class)->name('students.details')->can('Student Details List');
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
                    'prefix' => 'companies',
                    'as' => 'companies.',
                    'namespace' => 'Modules\PPUDS\Livewire\Pages\Company',
                ], function () {
                    Route::get('/', Index::class)->name('index')->can('Company View List');
                    Route::get('/add', Add::class)->name('add')->can('Company Create');
                    Route::get('/{company}/edit', Edit::class)->name('edit')->can('Company Update');
                    Route::get('/{company}/details', Details\Details::class)->name('details')->can('Company Details List');
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
                    Route::get('/', Index::class)->name('index')->can('Course View List');
                });

                Route::group([
                    'prefix' => 'registrations',
                    'as' => 'registrations.',
                    'namespace' => 'Modules\PPUDS\Livewire\Pages\Registration',
                ], function () {
                    Route::get('/', Index::class)->name('index')->can('Registration View List');
                    Route::get('/add', Add::class)->name('add')->can('Registration Create');
                    Route::get('/{registration}/edit', Edit::class)->name('edit')->can('Registration Update');
                });

                Route::group([
                    'prefix' => 'student-companies',
                    'as' => 'student-companies.',
                    'namespace' => 'Modules\PPUDS\Livewire\Pages\StudentCompany',
                ], function () {
                    Route::get('/', Index::class)->name('index')->can('StudentCompany View List');
                    Route::get('/add', Add::class)->name('add')->can('StudentCompany Create');
                    Route::get('/{studentCompany}/edit', Edit::class)->name('edit')->can('StudentCompany Update');
                });

                Route::group([
                    'prefix' => 'field-visits',
                    'as' => 'field-visits.',
                    'namespace' => 'Modules\PPUDS\Livewire\Pages\FieldVisit',
                ], function () {
                    Route::get('/', Index::class)->name('index')->can('FieldVisit View List');
                    Route::get('/add', Add::class)->name('add')->can('FieldVisit Create');
                    Route::get('/{fieldVisit}/edit', Edit::class)->name('edit')->can('FieldVisit Update');
                });

                Route::group([
                    'prefix' => 'announcements',
                    'as' => 'announcements.',
                    'namespace' => 'Modules\PPUDS\Livewire\Pages\Announcement',
                ], function () {
                    Route::get('/announcements', Index::class)->name('index')->can('Announcement View List');
                });

                Route::group([
                    'prefix' => 'leave-requests',
                    'as' => 'leave-requests.',
                    'namespace' => 'Modules\PPUDS\Livewire\Pages\LeaveRequest',
                ], function () {
                    Route::get('/', Index::class)->name('index')->can('LeaveRequest View List');
                });

                Route::group([
                    'prefix' => 'student-attendances',
                    'as' => 'student-attendances.',
                    'namespace' => 'Modules\PPUDS\Livewire\Pages\StudentAttendance',
                ], function () {
                    Route::get('/', Index::class)->name('index')->can('StudentAttendance View List');
                    Route::get('{studentAttendance}/report', Report::class)->name('report')->can('StudentAttendance Report List');
                });

                Route::group([
                    'prefix' => 'surveys',
                    'as' => 'surveys.',
                    'namespace' => 'Modules\PPUDS\Livewire\Pages\Survey',
                ], function () {
                    Route::get('/', Index::class)->name('index')->can('Survey View List');
                    Route::get('/add', Add::class)->name('add')->can('Survey Create');
                    Route::get('/{survey}/edit', Edit::class)->name('edit')->can('Survey Update');
                });

                Route::group([
                    'prefix' => 'chat-messages',
                    'as' => 'chat-messages.',
                ], function () {

                    Route::group([
                        'namespace' => 'Modules\PPUDS\Livewire\Pages\ChatMessage',
                    ], function () {
                        Route::get('/', Index::class)->name('index');
                    });

                    // Route::get('/chats', \Wirechat\Wirechat\Livewire\Chats\Chats::class)->name('chats'); 
                    Route::get('/{conversation}', \Wirechat\Wirechat\Livewire\Chat\Chat::class)->name('show'); 
                    
                }); 
            }); 
        }
    ); 
}); 
