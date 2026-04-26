<?php
// app/Http/Controllers/API/Customer/BookingController.php

namespace App\Http\Controllers\API\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreBookingRequest;
use App\Services\Customer\BookingService;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(
        private BookingService $bookingService
    ) {}

    /**
     * عرض جميع حجوزات الزبون
     * GET /api/customer/appointments
     */
    public function index(Request $request)
    {
        $status = $request->query('status');
        $result = $this->bookingService->getCustomerAppointments(auth()->user(), $status);

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }

    /**
     * عرض الحجوزات النشطة (قيد الانتظار + مؤكدة)
     * GET /api/customer/appointments/active
     */
    public function active()
    {
        $result = $this->bookingService->getActiveAppointments(auth()->user());

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }

    /**
     * عرض الحجوزات المنتهية (مكتملة + ملغية)
     * GET /api/customer/appointments/completed
     */
    public function completed()
    {
        $result = $this->bookingService->getCompletedAppointments(auth()->user());

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }

    /**
     * إنشاء حجز جديد (يدعم خدمات متعددة)
     * POST /api/customer/booking/store
     */
    public function store(StoreBookingRequest $request)
    {
        $result = $this->bookingService->storeBooking($request->validated());

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }

    /**
     * إلغاء حجز
     * POST /api/customer/appointments/{id}/cancel
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
