<?php

use App\Http\Middleware\EnforceConfiguredLoginMethod;
use Illuminate\Support\Facades\Route;
use Modules\PPUDS\Http\Controllers\KeycloakAuthController;
use Modules\PPUDS\Http\Controllers\LoginGatewayController;

// Route::get('/login', [KeycloakAuthController::class, 'redirect'])->name('login');

// Route::middleware('web')->group(function () {
//     Route::get('/auth/keycloak/callback', [KeycloakAuthController::class, 'callback'])->name('keycloak.callback');
//     Route::post('/logout', [KeycloakAuthController::class, 'logout'])->name('logout');
// });

Route::middleware('guest')->get('/login', LoginGatewayController::class)->name('login');

// مسارات Keycloak
Route::middleware(['guest', EnforceConfiguredLoginMethod::class])->group(function () {
    Route::get('/auth/keycloak/redirect', [KeycloakAuthController::class, 'redirect'])->name('keycloak.redirect');
    Route::get('/auth/keycloak/callback', [KeycloakAuthController::class, 'callback'])->name('keycloak.callback');
});

// مسار تسجيل الخروج الخاص بالنظامين
Route::middleware('auth')->post('/logout', [KeycloakAuthController::class, 'logout'])->name('logout');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::group(
        [
            'prefix' => \Mcamara\LaravelLocalization\Facades\LaravelLocalization::setLocale(),
            'middleware' => ['localeSessionRedirect', 'localizationRedirect', 'localeViewPath'],
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
                    'namespace' => 'Modules\PPUDS\Livewire\Pages\Supervisor',
                ], function () {
                    Route::get('/supervisors', Index::class)->name('supervisors.index')->can('Supervisor View List');
                    Route::get('/supervisors/{user}/details', Details\Details::class)->name('supervisors.details')->can('Supervisor Details List');
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
                    'prefix' => 'practical-supervisor-students',
                    'as' => 'practical-supervisor-students.',
                    'namespace' => 'Modules\PPUDS\Livewire\Pages\PracticalSupervisorStudent',
                ], function () {
                    Route::get('/', Index::class)->name('index')->can('PracticalSupervisorStudent View List');
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
                    Route::get('/import', ImportPlacements::class)->name('import')->can('StudentCompany Create');
                    Route::get('/{studentCompany}/edit', Edit::class)->name('edit')->can('StudentCompany Update');
                    Route::get('/{studentCompany}/details', Details::class)->name('details')->can('StudentCompany Details');
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
                    Route::get('/{announcement}/details', Details::class)->name('details')->can('Announcement Details');
                });

                Route::group([
                    'prefix' => '',
                    'as' => '',
                    'namespace' => 'Modules\PPUDS\Livewire\Pages\AnnouncementCategory',
                ], function () {
                    Route::get('/announcement-category', Index::class)->name('announcement-category.index')->can('Announcement Category View List');
                });

                Route::group([
                    'prefix' => 'alumni-report',
                    'as' => 'alumni-report.',
                    'namespace' => 'Modules\PPUDS\Livewire\Pages\AlumniReport',
                ], function () {
                    Route::get('/', Index::class)->name('index')->can('Alumni Report View List');
                });

                Route::group([
                    'prefix' => 'alumni-announcements',
                    'as' => 'alumni-announcements.',
                    'namespace' => 'Modules\PPUDS\Livewire\Pages\AlumniAnnouncement',
                ], function () {
                    Route::get('/', Index::class)->name('index')->can('Alumni Announcement View List');
                });

                Route::group([
                    'prefix' => 'banners',
                    'as' => 'banners.',
                    'namespace' => 'Modules\PPUDS\Livewire\Pages\Banner',
                ], function () {
                    Route::get('/', Index::class)->name('index')->can('Banner View List');
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
                    Route::get('/{survey}/details', Details\Details::class)->name('details');
                    Route::get('/{survey}/submissions/{user}/{studentCompany?}', Details\SubmissionDetails::class)->name('submission-details');
                    Route::get('/{survey}/edit', Edit::class)->name('edit')->can('Survey Update');
                    Route::get('/{survey}/survey-form', SurveyForm::class)->name('survey-form');
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
                    Route::get('/{conversation}', \Modules\PPUDS\Livewire\Pages\ChatMessage\Show::class)
                        ->middleware('belongsToConversation')
                        ->name('show');
                });

                Route::group([
                    'prefix' => 'notes',
                    'as' => 'notes.',
                    'namespace' => 'Modules\PPUDS\Livewire\Pages\Note',
                ], function () {
                    Route::get('/', Index::class)->name('index')->can('Note View List');
                    Route::get('/add', Add::class)->name('add')->can('Note Create');
                    Route::get('/{note}/edit', Edit::class)->name('edit')->can('Note Update');
                });

                Route::group([
                    'prefix' => 'reports',
                    'as' => 'reports.',
                    'namespace' => 'Modules\PPUDS\Livewire\Pages\Report',
                ], function () {
                    Route::get('/', Index::class)->name('index')->can('Report View List');
                    Route::get('/today', Today::class)->name('today')->can('Report View List');
                });

                Route::group([
                    'prefix' => 'absence-reports',
                    'as' => 'absence-reports.',
                    'namespace' => 'Modules\PPUDS\Livewire\Pages\AbsenceReport',
                ], function () {
                    Route::get('/', Index::class)->name('index')->can('Report View List');
                });

                Route::group([
                    'prefix' => 'non-compliance-reports',
                    'as' => 'non-compliance-reports.',
                    'namespace' => 'Modules\PPUDS\Livewire\Pages\NonComplianceReport',
                ], function () {
                    Route::get('/', Index::class)->name('index')->can('Report View List');
                });

                Route::group([
                    'prefix' => 'supervisor-reports',
                    'as' => 'supervisor-reports.',
                    'namespace' => 'Modules\PPUDS\Livewire\Pages\SupervisorReport',
                ], function () {
                    Route::get('/', Index::class)->name('index')->can('Supervisor Report View List');
                    Route::get('/{user}/details', Details::class)->name('details')->can('Supervisor Report View List');
                });

                Route::group([
                    'prefix' => 'sync-company-supervisors',
                    'as' => 'sync-company-supervisors.',
                    'namespace' => 'Modules\PPUDS\Livewire\Pages\SyncCompanySupervisors',
                ], function () {
                    Route::get('/', Index::class)->name('index')->can('Sync System Data View');
                });

                Route::group([
                    'prefix' => 'sync-system-data',
                    'as' => 'sync-system-data.',
                    'namespace' => 'Modules\PPUDS\Livewire\Pages\SyncSystemData',
                ], function () {
                    Route::get('/', Index::class)->name('index')->can('Sync System Data View');
                });
            });
        }
    );
});
