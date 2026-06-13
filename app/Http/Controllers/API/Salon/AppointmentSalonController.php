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
    $status = $request->query('status');
    $dateFrom = $request->query('date_from');
    $dateTo = $request->query('date_to');
    $barberId = $request->query('barber_id');
    $period = $request->query('period');
    $perPage = $request->query('per_page', 10);
    $page = $request->query('page', 1);


    $result = $this->bookingService->getSalonAppointments(
        auth()->user(),
        $search,
        $status,
        $dateFrom,
        $dateTo,
        $barberId,
        $period,  
        $perPage,
        $page
    );
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

    /**
     * إلغاء حجز معين (حجز واحد فقط)
     * POST /api/salon/appointments/{id}/cancel
     */
    public function cancel(Request $request, $id)
    {
        $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $this->bookingService->cancelAppointment(
            auth()->user(),
            (int) $id,
            $request->reason
        );

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }
}
