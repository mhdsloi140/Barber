<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CentersController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProfileController;
use Illuminate\Support\Facades\Route;

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
    return view('layout.app');
});
Route::get('login', [AuthController::class, 'index'])->name('admin.index');
Route::post('login', [AuthController::class, 'login'])->name('admin.login');
Route::post('/admin/logout', [AuthController::class, 'logout'])->name('admin.logout')->middleware('auth');
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/center', [CentersController::class, 'index'])->name('admin.center');
    Route::get('centers/{id}', [CentersController::class, 'show'])->name('admin.centers.show');
    Route::put('/admin/centers/{id}/activate', [CentersController::class, 'activate'])->name('admin.centers.activate');
    Route::put('/admin/centers/{id}/deactivate', [CentersController::class, 'deactivate'])->name('admin.centers.deactivate');
    Route::get('/admin/centers/{id}/json', [CentersController::class, 'getSalonJson'])->name('admin.centers.json');
    Route::get('/admin/centers/{id}', [CentersController::class, 'show'])->name('admin.centers.show');
    Route::delete('/admin/centers/{id}', [CentersController::class, 'destroy'])->name('admin.centers.destroy');
      Route::put('/admin/profile', [ProfileController::class, 'update'])->name('admin.profile.update');
    
    Route::post('/admin/profile/upload-image', [ProfileController::class, 'uploadImage'])->name('admin.profile.upload-image');
      Route::delete('/admin/profile/delete-image', [ProfileController::class, 'deleteImage'])->name('admin.profile.delete-image');
});
