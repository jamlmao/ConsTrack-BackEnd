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
    Route::get('/sortedTask/{project_id}', [PController::class, 'getSortedProjectTasks']);
    Route::get('/PtImages/{project_id}', [PController::class, 'getProjectTaskImages']);
    Route::get('/taskWdates/{project_id}', [PController::class, 'getProjectTasksGroupedByMonth']);
    

    Route::get('/projectCount', [PController::class, 'getAllProjectCounts']); //ADMIN

    Route::get('/clients-count-by-month', [AController::class, 'getClientsCountByMonth']); 
    Route::get('/staff-count-by-month', [AController::class, 'getStaffCountByMonth']); //not rendered
    Route::get('/projectsPM', [PController::class, 'getProjectsPerMonth']);
    Route::get('/staff/projects', [PController::class, 'getProjectsForStaff']);
    Route::get('/clients', [AController::class, 'getClientsUnderSameCompany']);
    Route::get('/clientsA', [AController::class, 'getAllClientsForAdmin']);
    Route::get('/user/details', [AController::class, 'getLoggedInUserNameAndId']);
    
    Route::get('/CompanyProjects/{staffId}', [PController::class, 'getProjectsCounts']);
    Route::get('/ProjectDetails/{projectId}', [PController::class, 'getProjectAndClientDetails']); 
    Route::post('/send-otp', [AController::class, 'sendOtp']); // to do
    Route::post('/update-password', [AController::class, 'updatePassword']); // to do
    Route::post('/generateSowa', [PController::class, 'downloadProjectsPdf']); // to do 
    Route::get('/getStaff', [AController::class, 'getAllStaff']); //not rendered Admin
    Route::get('/counts', [AController::class, 'getUserCounts']); //not rendered Admin
    Route::get('/projects', [PController::class, 'getAllProjectsFilteredByCompanies']); //not rendered admin
    Route::get('/monthly-counts', [AController::class, 'getMonthlyCounts']);//not rendered admin
});
