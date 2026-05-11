<?php
// app/Http/Controllers/API/Barber/BarberProfileController.php

namespace App\Http\Controllers\API\Barber;

use App\Http\Controllers\Controller;
use App\Http\Requests\Barber\UpdateBarberProfileRequest;
use App\Services\Barber\BarberProfileService;
use Illuminate\Http\Request;

class BarberProfileController extends Controller
{
    public function __construct(
        private BarberProfileService $profileService
    ) {}

     public function index()
    {
        $result = $this->profileService->getBarberStatistics(auth()->user());

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }

    /**
     * جلب عدد الخدمات المنجزة في شهر محدد
     * GET /api/barber/statistics/monthly?year=2026&month=5
     */
    public function monthlyCompletedServices(Request $request)
    {
        $year = $request->get('year', now()->year);
        $month = $request->get('month', now()->month);

        $result = $this->profileService->getMonthlyCompletedServices(
            auth()->user(),
            $year,
            $month
        );

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }

    /**
     * عرض الملف الشخصي للحلاق
     * GET /api/barber/profile
     */
    public function show()
    {
        $result = $this->profileService->getProfile(auth()->user());

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }

    /**
     * تحديث الملف الشخصي للحلاق
     * PUT /api/barber/profile
     */
    public function update(UpdateBarberProfileRequest $request)
    {
        $data = $request->validated();

        if ($request->hasFile('avatar')) {
            $data['avatar'] = $request->file('avatar');
        }

        $result = $this->profileService->updateProfile(auth()->user(), $data);

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }
}
