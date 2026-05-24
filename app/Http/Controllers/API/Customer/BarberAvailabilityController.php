<?php
// app/Http/Controllers/API/Customer/BarberAvailabilityController.php

namespace App\Http\Controllers\API\Customer;

use App\Http\Controllers\Controller;
use App\Services\Customer\BarberScheduleService;
use Illuminate\Http\Request;

class BarberAvailabilityController extends Controller
{
    public function __construct(
        private BarberScheduleService $barberScheduleService
    ) {}

    /**
     * جلب جدول الحلاق (أوقات الفراغ، الحجوزات، الخدمات)
     * GET /api/customer/barber/{barberId}/schedule?date=2026-05-25
     */
    public function getBarberSchedule($barberId, Request $request)
    {
        $request->validate([
            'date' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $result = $this->barberScheduleService->getBarberSchedule(
            (int) $barberId,
            $request->input('date')
        );

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }
}
