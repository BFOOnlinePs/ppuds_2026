<?php

use Illuminate\Support\Facades\Route;
use Modules\PPUDS\Http\Controllers\Api\V1\AnnouncementCategoryController;
use Modules\PPUDS\Http\Controllers\Api\V1\AnnouncementController;
use Modules\PPUDS\Http\Controllers\Api\V1\ChatController;
use Modules\PPUDS\Http\Controllers\Api\V1\CompanyCategoryController;
use Modules\PPUDS\Http\Controllers\Api\V1\CompanyController;
use Modules\PPUDS\Http\Controllers\Api\V1\CompanyDepartmentController;
use Modules\PPUDS\Http\Controllers\Api\V1\FieldVisitController;
use Modules\PPUDS\Http\Controllers\Api\V1\LeaveRequestController;
use Modules\PPUDS\Http\Controllers\Api\V1\NoteController;
use Modules\PPUDS\Http\Controllers\Api\V1\NonComplianceReportController;
use Modules\PPUDS\Http\Controllers\Api\V1\PaymentController;
use Modules\PPUDS\Http\Controllers\Api\V1\ProfileController;
use Modules\PPUDS\Http\Controllers\Api\V1\RegistrationController;
use Modules\PPUDS\Http\Controllers\Api\V1\ReportController;
use Modules\PPUDS\Http\Controllers\Api\V1\SettingsController;
use Modules\PPUDS\Http\Controllers\Api\V1\StudentAttendanceController;
use Modules\PPUDS\Http\Controllers\Api\V1\StudentAttendanceReportController;
use Modules\PPUDS\Http\Controllers\Api\V1\StudentCompanyAssistantController;
use Modules\PPUDS\Http\Controllers\Api\V1\StudentCompanyController;
use Modules\PPUDS\Http\Controllers\Api\V1\SupervisorStatisticsController;
use Modules\PPUDS\Http\Controllers\Api\V1\SurveyAnswerController;
use Modules\PPUDS\Http\Controllers\Api\V1\SurveyController;
use Modules\PPUDS\Http\Controllers\Api\V1\WorkExperienceController;

Route::prefix('v1')->as('api.v1.')->group(function () {

    Route::prefix('ppuds')->as('ppuds.')->group(function () {
        Route::controller(SettingsController::class)
            ->prefix('settings')
            ->as('settings.')
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::patch('/', 'update')->name('update');
            });
    });

    Route::middleware(['auth:api,sanctum', 'api.localize'])->group(function () {

        Route::prefix('ppuds')->as('ppuds.')->group(function () {

            Route::get('/me', [ProfileController::class, 'show'])->name('profile.me');

            Route::controller(SupervisorStatisticsController::class)
                ->prefix('supervisors')
                ->as('supervisors.')
                ->group(function () {
                    Route::get('/statistics', 'index')->name('statistics.index');
                    Route::get('/{supervisor}/statistics', 'show')->name('statistics.show');
                });

            Route::controller(CompanyController::class)
                ->prefix('companies')
                ->as('companies.')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/', 'store')->name('store');
                    Route::get('/{company}/branches/{branch}/location', 'updateBranchLocationMethodNotAllowed')
                        ->name('branches.location.method-not-allowed');
                    Route::patch('/{company}/branches/{branch}/location', 'updateBranchLocation')
                        ->name('branches.location.update');
                    Route::get('/{company}', 'show')->name('show');
                    Route::patch('/{company}', 'update')->name('update');
                });

            Route::controller(CompanyCategoryController::class)
                ->prefix('company-categories') // 2. تعديل الإملاء
                ->as('company-categories.')    // 2. تعديل الإملاء
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/', 'store')->name('store');
                    Route::get('/{company_category}', 'show')->name('show');
                });

            Route::controller(CompanyDepartmentController::class)
                ->prefix('company-departments')
                ->as('company-departments.')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/', 'store')->name('store');
                    Route::get('/{department}', 'show')->name('show');
                });

            Route::controller(StudentCompanyController::class)
                ->prefix('student-companies')
                ->as('student-companies.')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/', 'store')->name('store');
                    Route::get('/{studentCompany}', 'show')->name('show');
                    Route::delete('/{studentCompany}', 'destroy')->name('destroy');
                });

            Route::controller(StudentCompanyAssistantController::class)
                ->prefix('student-company-assistant')
                ->as('student-company-assistant.')
                ->group(function () {
                    Route::post('/students/search', 'searchStudents')->name('students.search');
                    Route::post('/companies/suggest', 'suggestCompanies')->name('companies.suggest');
                    Route::post('/companies/search', 'searchCompanies')->name('companies.search');
                    Route::post('/link', 'linkCompany')->name('link');
                    Route::post('/link-all', 'linkAllCompanies')->name('link-all');
                });

            Route::controller(StudentAttendanceController::class)
                ->prefix('attendances')
                ->as('attendances.')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::patch('/{studentAttendance}', 'update')->name('update');
                    Route::post('/check-in', 'checkIn')->name('check-in');
                    Route::post('/check-out', 'checkOut')->name('check-out');

                    Route::controller(StudentAttendanceReportController::class)
                        ->prefix('reports')
                        ->as('reports.')
                        ->group(function () {
                            Route::get('/', 'index')->name('index');
                            Route::post('/', 'store')->name('store');
                            Route::get('/{report}', 'show')->name('show');
                        });
                });

            Route::controller(PaymentController::class)
                ->prefix('payments')
                ->as('payments.')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/', 'store')->name('store');
                    Route::patch('/{payment}', 'update')->name('update');
                    Route::get('/{payment}', 'show')->name('show');
                });

            Route::controller(AnnouncementController::class)
                ->prefix('announcements')
                ->as('announcements.')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/', 'store')->name('store');
                    Route::get('/{announcement}', 'show')->name('show');
                });

            Route::controller(AnnouncementCategoryController::class)
                ->prefix('announcement-categories')
                ->as('announcement-categories.')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/', 'store')->name('store');
                    Route::get('/{announcement_category}', 'show')->name('show');
                });

            Route::controller(SurveyController::class)
                ->prefix('surveys')
                ->as('surveys.')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/', 'store')->name('store');
                    Route::get('/{survey}/evaluation-students', 'evaluationStudents')->name('evaluation-students');
                    Route::get('/{survey}', 'show')->name('show');
                });

            Route::controller(SurveyAnswerController::class)
                ->prefix('survey-answers')
                ->as('survey-answers.')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/', 'store')->name('store');
                });

            Route::controller(LeaveRequestController::class)
                ->prefix('leave-requests')
                ->as('leave-requests.')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/', 'store')->name('store');
                    Route::get('/{leaveRequest}', 'show')->name('show');
                    Route::patch('/{leaveRequest}', 'update')->name('update');
                });

            Route::controller(FieldVisitController::class)
                ->prefix('field-visits')
                ->as('field-visits.')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/', 'store')->name('store');
                    Route::get('/company-students', 'companyStudents')->name('company-students');
                    Route::post('/bulk', 'bulkStore')->name('bulk-store');
                    Route::get('/{fieldVisit}', 'show')->name('show');
                    Route::patch('/{fieldVisit}', 'update')->name('update');
                });

            Route::controller(RegistrationController::class)
                ->prefix('registrations')
                ->as('registrations.')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/', 'store')->name('store');
                    Route::get('/{registration}', 'show')->name('show');
                    Route::patch('/{registration}', 'update')->name('update');
                });

            Route::controller(ChatController::class)
                ->prefix('chats')
                ->as('chats.')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('/contacts', 'contacts')->name('contacts');
                    Route::post('/', 'store')->name('store');
                    Route::get('/{conversation}/messages', 'messages')->name('messages');
                    Route::post('/{conversation}/send', 'sendMessage')->name('send');
                    Route::patch('/{conversation}/read', 'markAsRead')->name('read');
                });

            Route::controller(NoteController::class)
                ->prefix('notes')
                ->as('notes.')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/', 'store')->name('store');
                    Route::get('/{note}', 'show')->name('show');
                });

            Route::controller(ReportController::class)
                ->prefix('reports')
                ->as('reports.')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('/{report}', 'show')->name('show');
                });

            Route::controller(NonComplianceReportController::class)
                ->prefix('non-compliance-reports')
                ->as('non-compliance-reports.')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                });

            Route::controller(WorkExperienceController::class)
                ->prefix('work-experiences')
                ->as('work-experiences.')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/', 'store')->name('store');
                    Route::get('/{workExperience}', 'show')->name('show');
                    Route::delete('/{workExperience}', 'destroy')->name('destroy');
                });
        });
    });
});
