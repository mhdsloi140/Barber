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
     * حفظ حجز جديد
     * POST /api/customer/booking/store
     */
    public function store(StoreBookingRequest $request)
    {
        $result = $this->bookingService->storeBooking($request->validated());
//    dd($result);
        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }
}
