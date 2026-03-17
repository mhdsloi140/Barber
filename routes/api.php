<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ProfileController;
use App\Http\Controllers\API\Salon\BarberController;
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


    Route::prefix('barbers')->group(function () {
        Route::post('/{id}/deactivate', [BarberController::class, 'deactivate'])->name('barbers.deactivate');
        Route::post('/{id}/activate', [BarberController::class, 'activate'])->name('barbers.activate');
        Route::post('/{id}/toggle', [BarberController::class, 'toggleStatus'])->name('barbers.toggle');
    });
});

