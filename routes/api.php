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
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\API\NotificationSettingsController;
use App\Http\Controllers\API\ProfileController;
use App\Http\Controllers\API\RatingController;
use App\Http\Controllers\API\Salon\AppointmentSalonController;
use App\Http\Controllers\API\Salon\AuthSalonController;
use App\Http\Controllers\API\Salon\BarberController;
use App\Http\Controllers\API\Salon\DashboardController;
use App\Http\Controllers\API\Salon\ProfileSalonController;
use App\Http\Controllers\API\Salon\SalonServiceController;
use App\Http\Controllers\API\Customer\SalonController;
use App\Http\Controllers\API\TestTopicsController;
use App\Http\Controllers\API\NotificationTestController;
use App\Services\Notification\FirebaseNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ===================== Routes المصادقة =====================
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->name('login');
    Route::post('register', [AuthController::class, 'register'])->name('register');
    Route::post('/verify-otp', [AuthController::class, 'verifyOTP']);
    Route::post('/resend-otp', [AuthController::class, 'resendOTP']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('/check-reset-otp', [AuthController::class, 'checkResetOTPStatus']);
    // Route::post('register/salon-owner', [App\Http\Controllers\API\Salon\AuthController::class, 'registerSalonOwner']);
});
Route::prefix('auth/register/salon-owner')->group(function () {

    // الخطوة 1: إرسال كود التحقق
    Route::post('send-otp', [AuthSalonController::class, 'sendOtp'])
        ->name('salon.register.send-otp');

    // الخطوة 2: إعادة إرسال كود التحقق
    Route::post('resend-otp', [AuthSalonController::class, 'resendOtp'])
        ->name('salon.register.resend-otp');

    // الخطوة 3: التحقق من الكود وإنشاء الحساب
    Route::post('verify', [AuthSalonController::class, 'verifyAndCreate'])
        ->name('salon.register.verify');
});
// ===================== Routes محمية (تتطلب مصادقة) =====================
Route::middleware(['auth:sanctum'])->group(function () {

    // FCM Token
    Route::post('/fcm-token', [FcmTokenController::class, 'update']);
    Route::delete('/fcm-token', [FcmTokenController::class, 'destroy']);

    // إعدادات الإشعارات
    Route::prefix('notifications/settings')->name('notifications.settings.')->group(function () {
        Route::get('/', [NotificationSettingsController::class, 'getStatus'])->name('status');
        Route::post('/enable', [NotificationSettingsController::class, 'enable'])->name('enable');
        Route::post('/disable', [NotificationSettingsController::class, 'disable'])->name('disable');
        Route::post('/toggle', [NotificationSettingsController::class, 'toggle'])->name('toggle');
        Route::put('/', [NotificationSettingsController::class, 'update'])->name('update');
    });

    // Device Tokens
    Route::post('/device-token', [DeviceTokenController::class, 'store']);
    Route::delete('/device-token', [DeviceTokenController::class, 'destroy']);

    // معلومات المستخدم
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Routes المصادقة
    Route::prefix('auth')->name('auth.')->group(function () {
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

// ===================== Routes صاحب الصالون =====================
Route::middleware(['auth:sanctum', 'role:salon_owner'])->prefix('salon-owner')->group(function () {
    Route::post('/fcm-token', [FcmTokenController::class, 'update']);
    Route::delete('/fcm-token', [FcmTokenController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'role:salon_owner'])->prefix('salons')->group(function () {
    Route::get('profile', [ProfileSalonController::class, 'show']);
    Route::post('profile', [ProfileSalonController::class, 'update']);
    Route::apiResource('barbers', BarberController::class);
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

// ===================== Routes الحلاق =====================
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
    Route::post('/working-hours/add-days', [WorkingHourController::class, 'addMultipleDays']);
    Route::delete('/working-hours/{day}', [WorkingHourController::class, 'deleteDay']);
    Route::get('salon-working-days', [WorkingHourController::class, 'getSalonWorkingDays']);

    Route::prefix('appointments')->group(function () {
        Route::get('show', [AppointmentController::class, 'index']);
        Route::get('pending', [AppointmentController::class, 'pending']);
        Route::post('{id}/approve', [AppointmentController::class, 'approve']);
        Route::post('{id}/reject', [AppointmentController::class, 'reject']);
        Route::post('{id}/cancel', [AppointmentController::class, 'cancel']);
    });

    Route::get('/statistics', [BarberProfileController::class, 'index']);
    Route::get('/statistics/monthly', [BarberProfileController::class, 'monthlyCompletedServices']);
});

// ===================== Routes الزبون =====================
Route::middleware(['auth:sanctum', 'role:customer'])->prefix('customer')->group(function () {
    Route::get('/dashboard', [DashboardCustomerController::class, 'index']);
    Route::get('salons', [SalonController::class, 'index']);
    Route::get('salons/{id}', [SalonController::class, 'show']);
    Route::get('salons/{id}/details', [SalonDetailsController::class, 'show']);

    Route::prefix('appointments')->group(function () {
        Route::get('/', [BookingController::class, 'index']);
        Route::get('active', [BookingController::class, 'active']);
        Route::get('pending', [BookingController::class, 'pending']);
        Route::get('confirmed', [BookingController::class, 'confirmed']);
        Route::get('completed', [BookingController::class, 'completed']);
        Route::get('cancelled', [BookingController::class, 'cancelled']);
        Route::get('upcoming', [BookingController::class, 'upcoming']);

        Route::get('{id}', [BookingController::class, 'show']);
        Route::post('{id}/cancel', [BookingController::class, 'cancel']);
        Route::post('{id}', [BookingController::class, 'update']);
    });

    Route::post('booking/store', [BookingController::class, 'store']);
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

// ===================== Routes التقييمات =====================
Route::prefix('ratings')->group(function () {
    Route::get('barber/{barberId}', [RatingController::class, 'barberRatings']);
    Route::get('salon/{salonId}', [RatingController::class, 'salonRatings']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/', [RatingController::class, 'store']);
        Route::get('my-ratings', [RatingController::class, 'myRatings']);
    });
});

// ===================== Routes لاختبار Topics =====================
Route::prefix('test/topics')->group(function () {
    Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
        Route::post('/all-customers', [TestTopicsController::class, 'sendToAllCustomers']);
        Route::post('/all-barbers', [TestTopicsController::class, 'sendToAllBarbers']);
        Route::post('/all-admins', [TestTopicsController::class, 'sendToAllAdmins']);
        Route::post('/salon/{salonId}/customers', [TestTopicsController::class, 'sendToSalonCustomers']);
        Route::post('/salon/{salonId}/barbers', [TestTopicsController::class, 'sendToSalonBarbers']);
        Route::post('/offer', [TestTopicsController::class, 'sendOffer']);
        Route::post('/custom', [TestTopicsController::class, 'sendToCustomTopic']);
        Route::get('/list', [TestTopicsController::class, 'getTopicsList']);
    });
    Route::post('/public/all-customers', [TestTopicsController::class, 'publicSendToAllCustomers']);
});

// ===================== Routes لاختبار الإشعارات =====================


// ===================== Routes الإشعارات =====================
Route::middleware(['auth:sanctum'])->prefix('notifications')->name('notifications.')->group(function () {

    // جلب جميع إشعارات المستخدم (مع Pagination)
    Route::get('/', [NotificationController::class, 'index'])->name('index');

    // جلب الإشعارات غير المقروءة فقط
    Route::get('/unread', [NotificationController::class, 'unread'])->name('unread');

    // عدد الإشعارات غير المقروءة (Badge)
    Route::get('/badge', [NotificationController::class, 'badge'])->name('badge');

    // تحديد إشعار كمقروء
    Route::put('/{id}/read', [NotificationController::class, 'markAsRead'])->name('mark-read');

    // تحديد جميع الإشعارات كمقروءة
    Route::put('/read-all', [NotificationController::class, 'markAllAsRead'])->name('mark-all-read');

    // حذف إشعار
    Route::delete('/{id}', [NotificationController::class, 'destroy'])->name('destroy');

    // حذف جميع الإشعارات
    Route::delete('/', [NotificationController::class, 'destroyAll'])->name('destroy-all');
});
