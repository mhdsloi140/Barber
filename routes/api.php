<?php

use App\Http\Controllers\API\AuthController;

use App\Http\Controllers\API\Barber\AppointmentController;
use App\Http\Controllers\API\Barber\BarberProfileController;
use App\Http\Controllers\API\Barber\WorkingHourController;
use App\Http\Controllers\API\Barbers\ServicesController;
use App\Http\Controllers\API\Customer\BookingController;
use App\Http\Controllers\API\Customer\DashboardCustomerController;
use App\Http\Controllers\API\Customer\FavoriteBarberController;
use App\Http\Controllers\API\Customer\FavoriteSalonController;
use App\Http\Controllers\API\Customer\SalonDetailsController;
use App\Http\Controllers\API\Notification\DeviceTokenController;
use App\Http\Controllers\API\Notification\FcmTokenController;
use App\Http\Controllers\API\ProfileController;
use App\Http\Controllers\API\RatingController;
use App\Http\Controllers\API\Salon\AppointmentSalonController;
use App\Http\Controllers\API\Salon\BarberController;
use App\Http\Controllers\API\Salon\DashboardController;
use App\Http\Controllers\API\Salon\ProfileSalonController;
use App\Http\Controllers\API\Salon\SalonServiceController;
use App\Http\Controllers\API\Customer\SalonController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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


Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->name('login');
    Route::post('register', [AuthController::class, 'register'])->name('register');
    Route::post('register/salon-owner', [App\Http\Controllers\API\Salon\AuthController::class, 'registerSalonOwner']);
});

Route::middleware(['auth:sanctum'])->group(function () {

    // FCM Token
    Route::post('/fcm-token', [FcmTokenController::class, 'update']);
    Route::delete('/fcm-token', [FcmTokenController::class, 'destroy']);

});
Route::middleware(['auth:sanctum'])->group(function () {

    // Device Tokens
    Route::post('/device-token', [DeviceTokenController::class, 'store']);
    Route::delete('/device-token', [DeviceTokenController::class, 'destroy']);

});
Route::middleware('auth:sanctum')->group(function () {


    Route::get('/user', function (Request $request) {
        return $request->user();
    });


    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('/', [ProfileController::class, 'index'])->name('index');
        Route::match(['put', 'patch'], '/', [ProfileController::class, 'updateProfile'])->name('update');
        Route::post('/avatar', [ProfileController::class, 'updateAvatar'])->name('avatar');
        Route::delete('/avatar', [ProfileController::class, 'deleteAvatar'])->name('avatar.delete');
    });


    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('/logout-all', [AuthController::class, 'logoutFromAllDevices'])->name('logout-all');
        Route::post('/refresh', [AuthController::class, 'refresh'])->name('refresh');
        Route::get('/me', [AuthController::class, 'me'])->name('me');
    });


    Route::post('/change-password', [ProfileController::class, 'changePassword'])->name('password.change');
});
/////     الصالونات او مدير الصالون
Route::middleware(['auth:sanctum', 'role:salon_owner'])->prefix('salon-owner')->group(function () {

    // FCM Token
    Route::post('/fcm-token', [FcmTokenController::class, 'update']);
    Route::delete('/fcm-token', [FcmTokenController::class, 'destroy']);

});
Route::middleware(['auth:sanctum', 'role:salon_owner'])->prefix('salons')->group(function () {

    Route::get('profile', [ProfileSalonController::class, 'show']);
    Route::post('profile', [ProfileSalonController::class, 'update']);
    Route::apiResource('barbers', BarberController::class);
    // عرض خدمات حلاق معين في الصالون
    Route::get('barbers/{barber_id}/services', [SalonServiceController::class, 'getBarberServices']);
    Route::get('barbers-count', [DashboardController::class, 'getBarbersCount']);
    Route::get('services', [SalonServiceController::class, 'index']);
    Route::prefix('barbers')->group(function () {
        Route::post('/{id}/deactivate', [BarberController::class, 'deactivate'])->name('barbers.deactivate');
        Route::post('/{id}/activate', [BarberController::class, 'activate'])->name('barbers.activate');
        Route::post('/{id}/toggle', [BarberController::class, 'toggleStatus'])->name('barbers.toggle');
    });
    Route::get('appointments', [AppointmentSalonController::class, 'index']);
    Route::post('appointments/{id}/cancel', [AppointmentSalonController::class, 'cancel']);
    Route::get('appointments/status/{status}', [AppointmentSalonController::class, 'getByStatus']);
});


//////////الحلاقين
Route::middleware(['auth:sanctum', 'role:barber'])->prefix('barber')->group(function () {

    Route::get('/profile', [BarberProfileController::class, 'show']);
    Route::post('/profile', [BarberProfileController::class, 'update']);

    Route::apiResource('services', ServicesController::class);
    Route::post('services/{id}/toggle', [ServicesController::class, 'toggleStatus']);
    Route::delete('services/{id}/force', [ServicesController::class, 'forceDelete']);
    Route::get('services/trashed', [ServicesController::class, 'trashed']);
    Route::post('services/{id}/restore', [ServicesController::class, 'restore']);
    Route::get('working-hours', [WorkingHourController::class, 'index']);
    Route::post('working-hours', [WorkingHourController::class, 'update']);
    Route::post('working-hours/reset', [WorkingHourController::class, 'reset']);
    // Route::post('/working-hours/add-day', [WorkingHourController::class, 'addDay']);
    Route::post('/working-hours/add-days', [WorkingHourController::class, 'addMultipleDays']);
    Route::delete('/working-hours/{day}', [WorkingHourController::class, 'deleteDay']);
    Route::get('salon-working-days', [WorkingHourController::class, 'getSalonWorkingDays']);
    // الحجوزات الموافة و عرض و رفض
    Route::prefix('appointments')->group(function () {
        Route::get('show', [AppointmentController::class, 'index']);
        Route::get('pending', [AppointmentController::class, 'pending']);
        Route::post('{id}/approve', [AppointmentController::class, 'approve']);
        Route::post('{id}/reject', [AppointmentController::class, 'reject']);
    });
    /// الاحصائيات
    Route::get('/statistics', [BarberProfileController::class, 'index']);
    Route::get('/statistics/monthly', [BarberProfileController::class, 'monthlyCompletedServices']);
});


//// الزبائن
Route::middleware(['auth:sanctum', 'role:customer'])->prefix('customer')->group(function () {

    Route::get('/dashboard', [DashboardCustomerController::class, 'index']);

    Route::get('salons', [SalonController::class, 'index']);
    Route::get('salons/{id}', [SalonController::class, 'show']);


    // حفظ الحجز الجديد
    Route::post('booking/store', [BookingController::class, 'store']);
    //عرض الخدمات او التفاصيل
    Route::get('salons/{id}/details', [SalonDetailsController::class, 'show']);
    //    Route::post('{id}/cancel', [BookingController::class, 'cancel']);

    Route::prefix('appointments')->group(function () {
        Route::get('/', [BookingController::class, 'index']);           // جميع الحجوزات
        Route::get('active', [BookingController::class, 'active']);     // الحجوزات النشطة
        Route::get('completed', [BookingController::class, 'completed']); // الحجوزات المنتهية
        Route::post('{id}/cancel', [BookingController::class, 'cancel']); // إلغاء حجز
    });
    ///المفصلات للحلاقين
    Route::get('/favorites', [FavoriteBarberController::class, 'index']);
    Route::post('/favorites', [FavoriteBarberController::class, 'store']);
    Route::get('/favorites/check/{barberId}', [FavoriteBarberController::class, 'check']);
    Route::delete('/favorites/{barberId}', [FavoriteBarberController::class, 'destroy']);
    /// الفضلات الصالون
    Route::get('/favorite-salons', [FavoriteSalonController::class, 'index']);
    Route::post('/favorite-salons', [FavoriteSalonController::class, 'store']);
    Route::get('/favorite-salons/check/{salonId}', [FavoriteSalonController::class, 'check']);
    Route::get('/favorite-salons/stats', [FavoriteSalonController::class, 'stats']);
    Route::delete('/favorite-salons/{salonId}', [FavoriteSalonController::class, 'destroy']);

});
Route::prefix('ratings')->group(function () {

    // مسارات عامة (عرض التقييمات)
    Route::get('barber/{barberId}', [RatingController::class, 'barberRatings']);
    Route::get('salon/{salonId}', [RatingController::class, 'salonRatings']);

    // مسارات محمية (تحتاج توثيق)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/', [RatingController::class, 'store']);
        Route::get('my-ratings', [RatingController::class, 'myRatings']);
    });
});
