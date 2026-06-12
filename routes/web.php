<?php

use App\Http\Controllers\Admin\AdvertisementController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CentersController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\FcmTokenController;
use App\Services\ultraMessage\UltraMsgService;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/





Route::get('/', function () {
    return view('layout.app');
});

// ===================== Routes المصادقة =====================
Route::get('login', [AuthController::class, 'index'])->name('admin.index');
Route::post('login', [AuthController::class, 'login'])->name('admin.login');
Route::post('/admin/logout', [AuthController::class, 'logout'])->name('admin.logout')->middleware('auth');
Route::middleware(['auth'])->group(function () {
    Route::post('/fcm-token', [FcmTokenController::class, 'update'])->name('fcm.token.update');
});
// ===================== Routes المحمية =====================
Route::middleware(['auth'])->group(function () {
    //  Route لحفظ FCM Token (المطلوب من JavaScript)
    Route::post('/admin/fcm/token', [FcmTokenController::class, 'update'])->name('admin.fcm.token');

    // Routes الإدارة (دور admin)
    Route::middleware(['role:admin'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
        Route::get('/center', [CentersController::class, 'index'])->name('admin.center');
        Route::get('centers/{id}', [CentersController::class, 'show'])->name('admin.centers.show');
        Route::put('/admin/centers/{id}/activate', [CentersController::class, 'activate'])->name('admin.centers.activate');
        Route::put('/admin/centers/{id}/deactivate', [CentersController::class, 'deactivate'])->name('admin.centers.deactivate');
        Route::get('/admin/centers/{id}/json', [CentersController::class, 'getSalonJson'])->name('admin.centers.json');
        Route::delete('/admin/centers/{id}', [CentersController::class, 'destroy'])->name('admin.centers.destroy');
        Route::put('/admin/profile', [ProfileController::class, 'update'])->name('admin.profile.update');
        Route::post('/admin/profile/upload-image', [ProfileController::class, 'uploadImage'])->name('admin.profile.upload-image');
        Route::delete('/admin/profile/delete-image', [ProfileController::class, 'deleteImage'])->name('admin.profile.delete-image');

        // الإعلانات
        Route::resource('ads', AdvertisementController::class);
        Route::post('ads/update-order', [AdvertisementController::class, 'updateOrder'])->name('ads.update-order');
        Route::post('ads/{ad}/toggle-status', [AdvertisementController::class, 'toggleStatus'])->name('ads.toggle-status');
        Route::post('ads/{ad}/duplicate', [AdvertisementController::class, 'duplicate'])->name('ads.duplicate');
        Route::get('/admin/ads/{ad}/json', [AdvertisementController::class, 'getJson'])->name('admin.ads.json');
    });
});

// ===================== Routes اختبار واتساب =====================
Route::get('/test-whatsapp', function (UltraMsgService $whatsapp) {
    return response()->json([
        'configured' => $whatsapp->isConfigured(),
        'instance_id' => config('services.ultramsg.instance_id') ? 'set' : 'not set',
        'api_token' => config('services.ultramsg.api_token') ? 'set' : 'not set',
    ]);
});

Route::get('/test-whatsapp-send', function (UltraMsgService $whatsapp) {
    $result = $whatsapp->sendMessage('9665XXXXXXXX', ' تم تفعيل واتساب بنجاح!');
    return response()->json($result);
});
