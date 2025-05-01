<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Registration\RegisterController;
use App\Http\Controllers\Registration\LoginController;
use App\Http\Controllers\Registration\GoogleAuthController;
use App\Http\Controllers\Registration\FacebookController;

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
Route::post('/register', [RegisterController::class, 'register']);
Route::post('/login', [LoginController::class, 'login']);


Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth:sanctum');
Route::post('/verify_otp', [RegisterController::class, 'verfication_otp'])->middleware('auth:sanctum');



Route::group(['middleware' => ['web']], function () {
    Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect']);
    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback']);
});



Route::group(['middleware' => ['web']], function () {
    Route::get('auth/facebook/redirect', [FacebookController::class, 'redirect']);
    Route::get('auth/facebook/callback', [FacebookController::class, 'callback']);
});






Route::middleware(['auth:sanctum', 'admin'])->group(function () {


});
