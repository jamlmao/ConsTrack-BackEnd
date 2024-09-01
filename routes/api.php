<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AController;
use App\Http\Controllers\PController;
use Dompdf\Dompdf;
use Dompdf\Options;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/



Route::post('/register', [AuthController::class, 'register']);
Route::post('/loginA', [AuthController::class, 'login']);




Route::group(['middleware'=> ['auth:sanctum']],function(){
    Route::post('/logout',[AuthController::class, 'logout']);
    Route::post('/registerS', [AController::class, 'createStaff']);
    Route::post('/registerC', [AController::class, 'createClient']);
    Route::put('staff/{id}', [AController::class, 'update']);
    Route::post('/addproject', [PController::class, 'addproject']);
    Route::post('/addtask/{project_id}', [PController::class, 'addTask']);
    Route::get('/projectsTasks/{project_id}', [PController::class, 'getProjectTasks']);
    Route::get('/staff/projects', [PController::class, 'getProjectsForStaff']);
    Route::get('/clients', [AController::class, 'getClientsUnderSameCompany']);
    Route::get('/user/details', [AController::class, 'getLoggedInUserNameAndId']);
    Route::get('/CompanyProjects/{staffId}', [PController::class, 'getProjectsCounts']);
    Route::get('/ProjectDetails/{projectId}', [PController::class, 'getProjectAndClientDetails']);
    Route::post('/send-otp', [AController::class, 'sendOtp']);
    Route::post('/update-password', [AController::class, 'updatePassword']);
    Route::post('/generateSowa', [PController::class, 'downloadProjectsPdf']);
   
});
