<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AController;
use App\Http\Controllers\PController;
use App\Http\Controllers\TaskController;
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
    Route::post('/updatetask/{task_id}', [PController::class, 'updateTask']);

    Route::get('/staff-with-extension', [AController::class, 'getStaffWithExtensionAndLicense']);
    Route::get('/projectsTasks/{project_id}', [PController::class, 'getProjectTasks']);
    Route::get('/projectD/{project_id}', [PController::class, 'getProjectDetails']);
    Route::get('/sortedTask/{project_id}', [PController::class, 'getSortedProjectTasks']);
    Route::get('/PtImages/{project_id}', [PController::class, 'getProjectTaskImages']);
    Route::get('/taskWdates/{project_id}', [PController::class, 'getProjectTasksGroupedByMonth']);
    Route::get('/tasksBycategory/{project_id}', [PController::class, 'getTasksByCategory']);
    
    
    Route::get('/Alltask', [TaskController::class, 'getAllTasksWithResources']);



    Route::get('/tasks/general', [TaskController::class, 'getGeneralTasks']);
    Route::get('/tasks/site', [TaskController::class, 'getSiteWorksTasks']);
    Route::get('/tasks/concrete', [TaskController::class, 'getConcreteWorksTasks']);
    Route::get('/tasks/metal', [TaskController::class, 'getMetalWorksTasks']);
    Route::get('/tasks/forms', [TaskController::class, 'getFormsAndScaffoldingWorksTasks']);
    Route::get('/tasks/steel', [TaskController::class, 'getSteelWorksTasks']);
    Route::get('/tasks/tins', [TaskController::class, 'getTinsWorksTasks']);
    Route::get('/tasks/plaster', [TaskController::class, 'getPlasterWorksTasks']);
    Route::get('/tasks/paint', [TaskController::class, 'getPaintsWorksTasks']);
    Route::get('/tasks/plumbing', [TaskController::class, 'getPlumbingWorksTasks']);
    Route::get('/tasks/electrical', [TaskController::class, 'getElectricalWorksTasks']);
    Route::get('/tasks/ceiling', [TaskController::class, 'getCeilingWorksTasks']);
    Route::get('/tasks/archi', [TaskController::class, 'getArchiWorksTasks']);

    Route::get('/projectCount', [PController::class, 'getAllProjectCounts']); //ADMIN
    Route::get('/admin/projects', [PController::class, 'getAllProjectsForAdmin']);
    Route::get('/admin/users', [AController::class, 'getAllUsers']);

    Route::get('/clients-count-by-month', [AController::class, 'getClientsCountByMonth']); 
    Route::get('/staff-count-by-month', [AController::class, 'getStaffCountByMonth']); //not rendered

    
    Route::get('/total', [PController::class, 'getTotalTaskCost']);
    Route::get('/projectsPM', [PController::class, 'getProjectsPerMonth']);
    Route::get('/staff/projects', [PController::class, 'getProjectsForStaff']);
    Route::get('/clients', [AController::class, 'getClientsUnderSameCompany']);
    Route::get('/clientsA', [AController::class, 'getAllClientsForAdmin']); // not used i think 
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
