<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AController;


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
Route::post('/loginA', [AuthController::class, 'loginAdmin']);




Route::group(['middleware'=> ['auth:sanctum']],function(){

    Route::post('/registerS', [AController::class, 'createStaff']);
    Route::post('/registerC', [AController::class, 'createClient']);
    Route::put('staff/{id}', [AController::class, 'update']);
});
