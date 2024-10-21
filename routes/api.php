<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AController;
use App\Http\Controllers\PController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ResourcesController;
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
Route::get('/projects/completed-and-ongoing', [PController::class, 'getCompletedAndOngoingProjects']);



Route::group(['middleware'=> ['auth:sanctum']],function(){
    Route::post('/logout',[AuthController::class, 'logout']);
    Route::post('/registerS', [AController::class, 'createStaff']);
    Route::post('/staff/create-staff', [AController::class, 'createStaffByAuthorizedStaff']);
    Route::post('/registerC', [AController::class, 'createClient']);
    Route::put('staff/{id}', [AController::class, 'update']);
    Route::post('/addproject', [PController::class, 'addproject']);
    Route::post('/addtask/{project_id}', [PController::class, 'addTask']);
 
    Route::post('/updatetask/{task_id}', [TaskController::class, 'updateTaskv2']);

    Route::put('projects/{projectId}/update-status', [PController::class, 'updateProjectStatus']);

    Route::get('/staff-with-extension', [AController::class, 'getStaffWithExtensionAndLicense']);
    Route::get('/fetchstaff', [AController::class, 'getAllStaffFromSameCompany']);
    Route::get('/projectsTasks/{project_id}', [PController::class, 'getProjectTasks']);
    Route::get('/projectD/{project_id}', [PController::class, 'getProjectDetails']);
    Route::get('/sortedTask/{project_id}', [PController::class, 'getSortedProjectTasks']);
   

    
    Route::get('/PtImages/{project_id}', [PController::class, 'getProjectTaskImages']);
    Route::get('/taskWdates/{project_id}', [PController::class, 'getProjectTasksGroupedByMonth']);
    Route::post('/refresh-tables', [PController::class, 'refreshTables']);
    
    
    Route::get('/Alltask', [TaskController::class, 'getAllTasksWithResources']);


    Route::post('/appointments', [AppointmentController::class, 'setAppointment']);
    Route::get('/staff/appointments', [AppointmentController::class, 'getStaffAppointments']);
    Route::put('/appointments/{id}/status', [AppointmentController::class, 'updateStatus']);
    Route::get('/notifications', [AppointmentController::class, 'getNotifications']);

   
    Route::get('/projectCount', [PController::class, 'getAllProjectCounts']); //ADMIN
    Route::get('/admin/projects', [PController::class, 'getAllProjectsForAdmin']);
    Route::get('/admin/users', [AController::class, 'getAllUsers']);

    Route::get('/clients-count-by-month', [AController::class, 'getClientsCountByMonth']); 
    Route::get('/clients/count-by-month', [AController::class, 'getClientCountByMonthA']);
    Route::get('/staffCountperMonth', [AController::class, 'getStaffCountByMonth']); 
    Route::get('/staff/CountPerMonthA', [AController::class, 'getStaffCountByMonthA']); 
    Route::get('/projects/count-by-month', [PController::class, 'getProjectCountByMonth']);

    
    Route::get('/total', [PController::class, 'getTotalTaskCost']);
    Route::get('/projectsPM', [PController::class, 'getProjectsPerMonth']);
    
    Route::get('/projectsY', [PController::class, 'getProjectPerYear']);
    Route::get('/staff/projects', [PController::class, 'getProjectsForStaff']);
    Route::get('/clients', [AController::class, 'getClientsUnderSameCompany']);
    Route::get('/clientsCount', [AController::class, 'getClientsUnderSameCompany2']);

    Route::get('/clientsA', [AController::class, 'getAllClientsForAdmin']); // not used i think 
    Route::get('/user/details', [AController::class, 'getLoggedInUserNameAndId']);
    
   

    Route::get('/CompanyProjects/{staffId}', [PController::class, 'getProjectsCounts']);
    Route::get('/ProjectDetails/{projectId}', [PController::class, 'getProjectAndClientDetails']); 
    


    Route::get('/getStaff', [AController::class, 'getAllStaff']); //not rendered Admin
    Route::get('/counts', [AController::class, 'getUserCounts']); //not rendered Admin
    Route::get('/projects', [PController::class, 'getAllProjectsFilteredByCompanies']); //not rendered admin
    Route::get('/monthly-counts', [AController::class, 'getMonthlyCounts']);//not rendered admin

    Route::get('clients/{clientId}/projects', [PController::class, 'getClientProjects']);


    Route::put('/clients/{clientId}', [AController::class, 'editClient']); 
    Route::put('/Etasks/{taskId}', [PController::class, 'editTask']);
    Route::put('/tasks/{taskId}/resources/{resourceId}', [PController::class, 'editTaskResources']);
    Route::put('/editCategory/{category_id}', [CategoryController::class, 'editCategory']);



    Route::post('/tasks/complete', [TaskController::class, 'CompleteTask']);
    Route::post('/addtask2/{project_id}', [PController::class, 'addTask']);
    Route::post('/addCategory/{project_id}', [PController::class, 'addCategory']);
    Route::get('/companies',[AController::class,"fetchAllCompanies"]);
    Route::get('categories/{project_id}', [CategoryController::class, 'getCategoriesByProjectId']);
    Route::get('categories/{category_id}/tasks', [TaskController::class, 'getTasksByCategoryId']); // fetch all task under that category
    Route::get('tasks/{task_id}/resources', [ResourcesController::class, 'getResourcesByTaskId']); //fetch all resources under that task and task details
    Route::get('tasks/{task_id}/used-resources', [ResourcesController::class, 'getUsedResourcesForTask']);
    Route::get('/sortedTask2/{project_id}', [PController::class, 'getSortedProjectTasks3']);
    Route::get('/tasksBycategory/{project_id}', [PController::class, 'getTasksByCategory']);
    Route::get('/taskImages/{task_id}', [TaskController::class, 'getTaskImages']);

    Route::get('/projects/{projectId}/tasks', [TaskController::class, 'getAllProjectTasks']);
    
    Route::get('/history/{projectId}', [PController::class, 'getProjectHistory']);
    Route::post('/insert-available-dates', [AppointmentController::class, 'insertAvailableDates']);
    Route::post('/admin/update-password', [AController::class, 'updatePassword']);
    Route::get('/available-dates', [AppointmentController::class, 'getAvailableDates2']);
    Route::get('/client/available-dates', [AppointmentController::class, 'getAvailableDatesWithStatus']);
    Route::put('/category/remove/{categoryId}', [CategoryController::class, 'removeCategory']);
    Route::put('/task/remove/{taskId}', [TaskController::class, 'removeTask']);
});
