<?php
// app/Http/Controllers/API/Barber/AppointmentController.php

namespace App\Http\Controllers\API\Barber;

use App\Http\Controllers\Controller;
use App\Http\Requests\Barber\ApproveAppointmentRequest;
use App\Http\Requests\Barber\RejectAppointmentRequest;
use App\Services\Barber\BookingService;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    public function __construct(
        private BookingService $bookingService
    ) {}

  /// عرض جميع الحجوزات التابعة للحلاق
    public function index()
    {
        $result = $this->bookingService->getBarberAppointments(auth()->user());

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }

///الججزات قيد الانتضار
    public function pending()
    {
        $result = $this->bookingService->getPendingAppointments(auth()->user());

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }

  //تاكيد الحجز
    public function approve(ApproveAppointmentRequest $request)
    {
        $result = $this->bookingService->approveAppointment(
            auth()->user(),
            $request->id
        );

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }

    // رفض الحجز
    public function reject(RejectAppointmentRequest $request)
    {
        $result = $this->bookingService->rejectAppointment(
            auth()->user(),
            $request->id,
            $request->reason
        );

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }
}
