<?php

use App\Http\Controllers\API\AuthController;

use App\Http\Controllers\API\Barber\WorkingHourController;
use App\Http\Controllers\API\Barbers\ServicesController;
use App\Http\Controllers\API\ProfileController;
use App\Http\Controllers\API\Salon\BarberController;
use App\Http\Controllers\API\Salon\DashboardController;
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

Route::middleware(['auth:sanctum', 'role:salon_owner'])->prefix('salons')->group(function () {


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
});
Route::middleware(['auth:sanctum', 'role:barber'])->prefix('barber')->group(function () {


    Route::apiResource('services', ServicesController::class);


    Route::post('services/{id}/toggle', [ServicesController::class, 'toggleStatus']);
    Route::delete('services/{id}/force', [ServicesController::class, 'forceDelete']);
    Route::get('services/trashed', [ServicesController::class, 'trashed']);
    Route::post('services/{id}/restore', [ServicesController::class, 'restore']);
    Route::get('working-hours', [WorkingHourController::class, 'index']);
    Route::put('working-hours', [WorkingHourController::class, 'update']);
    Route::post('working-hours/reset', [WorkingHourController::class, 'reset']);
});

Route::prefix('customer')->group(function () {

    // عرض الصالونات
    Route::get('salons', [SalonController::class, 'index']);
    Route::get('salons/{id}', [SalonController::class, 'show']);
});
