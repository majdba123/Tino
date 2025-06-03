<?php

use App\Http\Controllers\ClinicController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Registration\RegisterController;
use App\Http\Controllers\Registration\LoginController;
use App\Http\Controllers\Registration\GoogleAuthController;
use App\Http\Controllers\Registration\FacebookController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\UserSubscriptionController;
use App\Http\Controllers\DiscountCouponController;
use App\Http\Controllers\MedicalRecordController;
use App\Http\Controllers\PetController;
use App\Http\Controllers\ConsultationController;
use App\Http\Controllers\OrderClinicController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\PillController;

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
    Route::prefix('admin')->group(function () {
        Route::prefix('subscriptions')->group(function () {
            Route::get('fillter/', [SubscriptionController::class, 'index']);
            Route::get('get_all_user_subscriped/', [SubscriptionController::class, 'get_all_user_subscriped']);
            Route::get('get_subscripe_of_user/{user_id}', [UserSubscriptionController::class, 'activeSubscription']);
            Route::get('show/{id}', [SubscriptionController::class, 'show']);
            Route::post('store/', [SubscriptionController::class, 'store']);
            Route::put('update/{id}', [SubscriptionController::class, 'update']);
            Route::delete('delete/{id}', [SubscriptionController::class, 'destroy']);
            Route::post('active/{id}/', [SubscriptionController::class, 'activate']);
            Route::post('deactive/{id}/', [SubscriptionController::class, 'deactivate']);
        });


        Route::prefix('clinics')->group(function () {
            Route::post('/store', [ClinicController::class, 'store']);
            Route::put('/update/{id}', [ClinicController::class, 'update']); // PUT أو PATCH
            Route::get('fillter', [ClinicController::class, 'filter']);
            Route::get('getClinicStatistics/{clinic_id}', [ClinicController::class, 'getClinicStatistics']);
            Route::get('show/{clinic_id}', [ClinicController::class, 'show']);
            Route::delete('/delete/{clinic_id}', [ClinicController::class, 'destroy']);

        });


        Route::prefix('consultations')->group(function () {
            Route::get('get_all/', [ConsultationController::class, 'index']);
            Route::get('/show/{id}', [ConsultationController::class, 'show']);
            Route::post('/change_operation/{id}', [ConsultationController::class, 'change_operation']);
            Route::post('/reassignToClinic/{id}', [ConsultationController::class, 'reassignToClinic']);

        });

        Route::prefix('employees')->group(function () {
            Route::post('store/', [EmployeeController::class, 'store']);
            Route::put('update/{id}', [EmployeeController::class, 'update']);
            Route::get('index/', [EmployeeController::class, 'index']);
            Route::delete('delete/{id}', [EmployeeController::class, 'destroy']);
        });


        Route::prefix('order')->group(function () {
            Route::get('/fillter', [OrderClinicController::class, 'getClinicOrders']);
            Route::get('/show_clinic_order/{clinic_id}', [OrderClinicController::class, 'show_clinic_order']);
            Route::get('show/{id}', [OrderClinicController::class, 'showOrder']);

        });


        Route::prefix('contact')->group(function () {
            Route::post('/store_reply/{contact_id}', [ContactController::class, 'storeReply']);
            Route::get('/get_all', [ContactController::class, 'allContacts']);
            Route::delete('delete/{contact_id}', [ContactController::class, 'destroy']);
          });



        Route::prefix('pill')->group(function () {
            Route::get('/pdf/{pill_id}', [PillController::class, 'showPdfApi']);
            Route::get('/get_all', [PillController::class, 'index']);
            Route::get('/show/{pill_id}', [PillController::class, 'show']);

        });


        Route::prefix('user')->group(function () {
            Route::get('/get_all', [UserController::class, 'getAllUsers']);
            Route::get('/show/{id}', [UserController::class, 'getUserById']);
            Route::delete('/delete/{pill_id}', [UserController::class, 'deleteUser']);

        });


    });





});

###########################################################################################################################################
###########################################################################################################################################
###########################################################################################################################################
###########################################################################################################################################


Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('user')->group(function () {
        Route::prefix('subscriptions')->group(function () {
            Route::get('fillter/', [SubscriptionController::class, 'index']);
            Route::get('show/{id}', [SubscriptionController::class, 'show']);

            Route::post('subscribe/', [UserSubscriptionController::class, 'subscribe']);
            Route::get('get_my_all', [UserSubscriptionController::class, 'get_my_all']);

        });

        Route::post('pets/store', [PetController::class, 'store']);
        Route::put('pets/update/{id}', [PetController::class, 'updatePet']);

        Route::get('pets/get_all', [PetController::class, 'index']);
        Route::post('/medical-records/store', [MedicalRecordController::class, 'store']);
        Route::get('discount-coupons/get_all', [DiscountCouponController::class, 'index']);



        Route::prefix('consultations')->group(function () {
        Route::post('store/', [ConsultationController::class, 'store']);
        Route::get('get_all/', [ConsultationController::class, 'index']);
        Route::get('/show/{id}', [ConsultationController::class, 'show']);

        });

        Route::prefix('profile')->group(function () {
            Route::post('/update', [UserController::class, 'updateProfile']);
            Route::get('/my_info', [UserController::class, 'getProfile']);
        });



        Route::prefix('contact')->group(function () {
            Route::post('/store', [ContactController::class, 'store']);
            Route::get('/my_contact', [ContactController::class, 'myContacts']);

         });




    });
});

###########################################################################################################################################
###########################################################################################################################################
###########################################################################################################################################
###########################################################################################################################################

Route::middleware(['auth:sanctum' , 'clinic'])->group(function () {
    Route::prefix('clinic')->group(function () {
        Route::prefix('order')->group(function () {
            Route::get('fillter/', [OrderClinicController::class, 'getClinicOrders']);
            Route::get('show/{id}', [OrderClinicController::class, 'showOrder']);
            Route::post('change_status/{order_id}', [OrderClinicController::class, 'updateOrderStatus']);
            Route::post('QR/', [OrderClinicController::class, 'checkAndUpdateOrderStatus']);
            Route::post('finished_order/', [OrderClinicController::class, 'completeOrder']);


        });

        Route::prefix('profile')->group(function () {
            Route::post('/update', [UserController::class, 'updateClinicProfile']);
            Route::get('/my_info', [UserController::class, 'getClinicProfile']);
        });

        Route::get('/dashboard', [ClinicController::class, 'getClinicStatistics']);

    });
});





###########################################################################################################################################
###########################################################################################################################################
###########################################################################################################################################
###########################################################################################################################################


Route::middleware(['auth:sanctum' , 'employee'])->group(function () {

    Route::prefix('employee')->group(function () {
            Route::prefix('clinics')->group(function () {
            Route::get('fillter', [ClinicController::class, 'filter']);
            Route::get('show/{clinic_id}', [ClinicController::class, 'show']);

        });


        Route::prefix('consultations')->group(function () {
            Route::get('get_all/', [ConsultationController::class, 'index']);
            Route::get('/show/{id}', [ConsultationController::class, 'show']);
            Route::post('/change_operation/{id}', [ConsultationController::class, 'change_operation']);
            Route::post('/reassignToClinic/{id}', [ConsultationController::class, 'reassignToClinic']);
        });

    });
});
