<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Auth;




/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});


Route::get('/private', function () {
    return view('chat');
});


Route::get('/public', function () {
    return view('notification');
});






Route::post('/stripe/webhook', [PaymentController::class, 'handleWebhook']);



Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

