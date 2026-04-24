<?php
// app/Http/Controllers/API/Salon/AppointmentSalonController.php

namespace App\Http\Controllers\API\Salon;

use App\Http\Controllers\Controller;
use App\Services\Salon\BookingService;
use App\Services\Salon\SalonBookingService;
use Illuminate\Http\Request;

class AppointmentSalonController extends Controller
{
    public function __construct(
        private SalonBookingService $bookingService
    ) {}

    /**
     * عرض جميع حجوزات الصالون (مع إمكانية البحث عن حلاق)
     * GET /api/salon/appointments?search=اسم_الحلاق
     */
    public function index(Request $request)
    {

        $search = $request->query('search');

        $result = $this->bookingService->getSalonAppointments(auth()->user(), $search);

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }

    /**
     * عرض حجوزات حسب الحالة
     */
    public function getByStatus($status)
    {
        $result = $this->bookingService->getSalonAppointmentsByStatus(auth()->user(), $status);
        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }
}
