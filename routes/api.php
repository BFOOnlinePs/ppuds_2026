<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Modules\Marketing\Http\Controllers\Api\V1\LoyaltyRuleController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can registration API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::prefix('v1')->as('api.v1.')->group(function () {

//     Route::middleware(['auth:sanctum', 'api.localize'])->group(function () {

//         Route::prefix('marketing')->as('marketing.')->group(function () {

//             Route::controller(LoyaltyRuleController::class)
//                 ->prefix('loyalty-rules')
//                 ->as('loyalty-rules.')
//                 ->group(function () {
//                     Route::get('/', 'index')->name('index');
//                     Route::get('/{loyaltyRule}', 'show')->name('show');
//                 });

//         });

//     });

// });

Broadcast::routes(['middleware' => ['auth:sanctum']]);