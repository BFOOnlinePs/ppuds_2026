<?php

use Illuminate\Support\Facades\Route;
use Modules\PPUDS\Http\Controllers\Api\V1\ChatController;
use Modules\PPUDS\Http\Controllers\Api\V1\AnnouncementController;
use Modules\PPUDS\Http\Controllers\Api\V1\CompanyCategoryController;
use Modules\PPUDS\Http\Controllers\Api\V1\CompanyController;
use Modules\PPUDS\Http\Controllers\Api\V1\CompanyDepartmentController;
use Modules\PPUDS\Http\Controllers\Api\V1\LeaveRequestController;
use Modules\PPUDS\Http\Controllers\Api\V1\PaymentController;
use Modules\PPUDS\Http\Controllers\Api\V1\StudentAttendanceController;
use Modules\PPUDS\Http\Controllers\Api\V1\StudentAttendanceReportController;
use Modules\PPUDS\Http\Controllers\Api\V1\StudentCompanyController;
use Modules\PPUDS\Http\Controllers\Api\V1\SurveyAnswerController;
use Modules\PPUDS\Http\Controllers\Api\V1\SurveyController;

Route::prefix('v1')->as('api.v1.')->group(function () {
    Route::middleware(['auth:sanctum', 'api.localize'])->group(function () {

        Route::prefix('ppuds')->as('ppuds.')->group(function () {

            Route::controller(CompanyController::class)
                ->prefix('companies')
                ->as('companies.')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/', 'store')->name('store');
                    Route::get('/{company}', 'show')->name('show');
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
                });

            Route::controller(StudentAttendanceController::class)
                ->prefix('attendances')
                ->as('attendances.')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/check-in', 'checkIn')->name('check-in');
                    Route::post('/check-out', 'checkOut')->name('check-out');
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

            Route::controller(StudentAttendanceReportController::class)
                ->prefix('reports')
                ->as('reports.')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/', 'store')->name('store');
                    Route::get('/{report}', 'show')->name('show');
                });

            Route::controller(AnnouncementController::class)
                ->prefix('announcements')
                ->as('announcements.')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/', 'store')->name('store');
                    Route::get('/{announcement}', 'show')->name('show');
                });

            Route::controller(SurveyController::class)
                ->prefix('surveys')
                ->as('surveys.')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/', 'store')->name('store');
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

            Route::controller(ChatController::class)
                ->prefix('chats')
                ->as('chats.')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('/{conversation}/messages', 'messages')->name('messages');
                    Route::post('/{conversation}/send', 'sendMessage')->name('send');
                    Route::patch('/{conversation}/read', 'markAsRead')->name('read');
                });
        });
    });

});
