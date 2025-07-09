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
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\UserReviewController;
use App\Http\Controllers\ForgetPasswordController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\AdminController;

use App\Helpers\OtpHelper;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

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

// Configure rate limiting
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
});

// Public routes with rate limiting
Route::middleware(['throttle:api'])->group(function () {
    Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/register', [RegisterController::class, 'register']);
    Route::post('/login', [LoginController::class, 'login']);
    Route::post('/verify_otp', [RegisterController::class, 'verfication_otp'])->middleware('auth:sanctum');

    Route::post('/forget-password', [ForgetPasswordController::class, 'forgetPassword'])->middleware('auth:sanctum');
    Route::post('/reset-password', [ForgetPasswordController::class, 'resetPasswordByVerifyOtp'])->middleware('auth:sanctum');

    Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth:sanctum');

    Route::group(['middleware' => ['web']], function () {
        Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect']);
        Route::get('auth/google/callback', [GoogleAuthController::class, 'callback']);
    });

    Route::get('/payment/success/{subscription}', [PaymentController::class, 'success']);
    Route::get('/payment/cancel/{subscription}', [PaymentController::class, 'cancel']);
});

// Authenticated user routes with rate limiting
Route::middleware(['auth:sanctum', 'banned', 'throttle:api'])->group(function () {
    Route::prefix('user')->group(function () {
        Route::prefix('subscriptions')->group(function () {
            Route::get('fillter/', [SubscriptionController::class, 'index']);
            Route::get('show/{id}', [SubscriptionController::class, 'show']);

            Route::post('subscribe/', [UserSubscriptionController::class, 'subscribe'])->middleware('otp');
            Route::get('get_my_all', [UserSubscriptionController::class, 'get_my_all']);
        });

        Route::post('pets/store', [PetController::class, 'store'])->middleware('otp');
        Route::put('pets/update/{id}', [PetController::class, 'updatePet'])->middleware('otp');

        Route::get('pets/get_all', [PetController::class, 'index']);
        Route::post('/medical-records/store', [MedicalRecordController::class, 'store']);
        Route::get('discount-coupons/get_all', [DiscountCouponController::class, 'index']);

        Route::prefix('consultations')->group(function () {
            Route::post('store/', [ConsultationController::class, 'store'])->middleware('otp');
            Route::get('get_all/', [ConsultationController::class, 'index'])->middleware('otp');
            Route::get('/show/{id}', [ConsultationController::class, 'show']);
        });

        Route::prefix('profile')->group(function () {
            Route::post('/update', [UserController::class, 'updateProfile']);
            Route::get('/my_info', [UserController::class, 'getProfile']);
        });

        Route::prefix('contact')->group(function () {
            Route::post('/store', [ContactController::class, 'store'])->middleware('otp');
            Route::get('/my_contact', [ContactController::class, 'myContacts']);
        });

        Route::prefix('review')->group(function () {
            Route::get('/get_all', [UserReviewController::class, 'getUserRatings'])->middleware('otp');
            Route::put('/update/{rating}', [UserReviewController::class, 'update']);
            Route::delete('/delete/{rating}', [UserReviewController::class, 'destroy']);
        });
    });
});

// Admin routes with rate limiting
Route::middleware(['auth:sanctum', 'admin', 'throttle:api'])->group(function () {
    Route::prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'getDashboardStats']);

        Route::post('notification/sendAdminNotification', [ChatController::class, 'sendAdminNotification']);

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
            Route::put('/update/{id}', [ClinicController::class, 'update']);
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
            Route::post('/update_status/{id}', [UserController::class, 'updateUserStatus']);
        });

        Route::prefix('review')->group(function () {
            Route::get('/get_all', [UserReviewController::class, 'getAllRatings']);
            Route::delete('/delete/{id}', [UserReviewController::class, 'adminDestroy']);
        });
    });
});

// Clinic routes with rate limiting
Route::middleware(['auth:sanctum', 'clinic', 'bannd', 'throttle:api'])->group(function () {
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

        Route::prefix('review')->group(function () {
            Route::get('/get_all', [UserReviewController::class, 'getClinicRatings']);
        });

        Route::get('/dashboard', [ClinicController::class, 'getClinicStatistics']);
    });
});

// Employee routes with rate limiting
Route::middleware(['auth:sanctum', 'employee', 'bannd', 'throttle:api'])->group(function () {
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

// Notification routes with rate limiting
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::prefix('notification')->group(function () {
        Route::get('get_all', [ChatController::class, 'getUserNotifications']);
        Route::post('mark_read', [ChatController::class, 'markAllAsRead']);
    });
});
