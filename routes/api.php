<?php

use App\Http\Controllers\API\AuthController;

use App\Http\Controllers\API\Barber\AppointmentController;
use App\Http\Controllers\API\Barber\BarberProfileController;
use App\Http\Controllers\API\Barber\WorkingHourController;
use App\Http\Controllers\API\Barbers\ServicesController;
use App\Http\Controllers\API\Customer\BarberAvailabilityController;
use App\Http\Controllers\API\Customer\BookingController;
use App\Http\Controllers\API\Customer\DashboardCustomerController;
use App\Http\Controllers\API\Customer\FavoriteBarberController;
use App\Http\Controllers\API\Customer\FavoriteSalonController;
use App\Http\Controllers\API\Customer\SalonDetailsController;
use App\Http\Controllers\API\Notification\DeviceTokenController;
use App\Http\Controllers\API\Notification\FcmTokenController;
use App\Http\Controllers\API\NotificationSettingsController;
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
    Route::post('/verify-otp', [AuthController::class, 'verifyOTP']);
    Route::post('/resend-otp', [AuthController::class, 'resendOTP']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('/check-reset-otp', [AuthController::class, 'checkResetOTPStatus']);
    Route::post('register/salon-owner', [App\Http\Controllers\API\Salon\AuthController::class, 'registerSalonOwner']);
});

Route::middleware(['auth:sanctum'])->group(function () {

    // FCM Token
    Route::post('/fcm-token', [FcmTokenController::class, 'update']);
    Route::delete('/fcm-token', [FcmTokenController::class, 'destroy']);
    Route::prefix('notifications/settings')->name('notifications.settings.')->group(function () {
        Route::get('/', [NotificationSettingsController::class, 'getStatus'])->name('status');
        Route::post('/enable', [NotificationSettingsController::class, 'enable'])->name('enable');
        Route::post('/disable', [NotificationSettingsController::class, 'disable'])->name('disable');
        Route::post('/toggle', [NotificationSettingsController::class, 'toggle'])->name('toggle');
        Route::put('/', [NotificationSettingsController::class, 'update'])->name('update');
    });

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

    Route::prefix('auth')->name('auth.')->group(function () {

        //  نقل Routes الـ profile إلى داخل auth
        Route::prefix('profile')->name('profile.')->group(function () {
            Route::get('/', [ProfileController::class, 'index'])->name('index');
            Route::post('/', [ProfileController::class, 'update'])->name('update');
            Route::post('/avatar', [ProfileController::class, 'updateAvatar'])->name('avatar');
            Route::delete('/avatar', [ProfileController::class, 'deleteAvatar'])->name('avatar.delete');
        });

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

    // الصالونات
    Route::get('salons', [SalonController::class, 'index']);
    Route::get('salons/{id}', [SalonController::class, 'show']);
    Route::get('salons/{id}/details', [SalonDetailsController::class, 'show']);

    Route::prefix('appointments')->group(function () {

        Route::get('/', [BookingController::class, 'index']);           // جميع الحجوزات
        Route::get('active', [BookingController::class, 'active']);     // النشطة (pending + confirmed + مستقبلية)
        Route::get('pending', [BookingController::class, 'pending']);   // قيد الانتظار فقط
        Route::get('confirmed', [BookingController::class, 'confirmed']); // المؤكدة فقط
        Route::get('completed', [BookingController::class, 'completed']); // المكتملة فقط
        Route::get('cancelled', [BookingController::class, 'cancelled']); // الملغية فقط

        // Routes مع {id} (تأكد من وضعها في النهاية)
        Route::get('{id}', [BookingController::class, 'show']);         // تفاصيل حجز محدد
        Route::post('{id}/cancel', [BookingController::class, 'cancel']);
        Route::post('{id}', [BookingController::class, 'update']);
    });

    // حفظ الحجز الجديد (خارج مجموعة appointments لأن له مسار مختلف)
    Route::post('booking/store', [BookingController::class, 'store']);
    // Route::put('booking/{id}', [BookingController::class, 'update']);


    // جدول الحلاق
    Route::get('barber/{barberId}/schedule', [BarberAvailabilityController::class, 'getBarberSchedule']);

    // المفضلة - حلاقين
    Route::prefix('favorites')->group(function () {
        Route::get('/', [FavoriteBarberController::class, 'index']);
        Route::post('/', [FavoriteBarberController::class, 'store']);
        Route::get('check/{barberId}', [FavoriteBarberController::class, 'check']);
        Route::delete('{barberId}', [FavoriteBarberController::class, 'destroy']);
    });

    // المفضلة - صالونات
    Route::prefix('favorite-salons')->group(function () {
        Route::get('/', [FavoriteSalonController::class, 'index']);
        Route::post('/', [FavoriteSalonController::class, 'store']);
        Route::get('check/{salonId}', [FavoriteSalonController::class, 'check']);
        Route::get('stats', [FavoriteSalonController::class, 'stats']);
        Route::delete('{salonId}', [FavoriteSalonController::class, 'destroy']);
    });

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
