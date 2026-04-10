<?php
// app/Http/Controllers/API/Customer/SalonController.php

namespace App\Http\Controllers\API\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\SalonSearchRequest;
use App\Http\Requests\Customer\SalonShowRequest;
use App\Services\Customer\SalonService;
use Illuminate\Http\Request;

class SalonController extends Controller
{
    public function __construct(
        private SalonService $salonService
    ) {}

    /**
     * عرض جميع الصالونات
     * GET /api/customer/salons
     */
    public function index(SalonSearchRequest $request)
    {
        $result = $this->salonService->getSalons($request->validated());

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }

    /**
     * عرض صالون محدد
     * GET /api/customer/salons/{id}
     */
    public function show($id, SalonShowRequest $request)
    {
        $result = $this->salonService->getSalon(
            $id,
            $request->input('latitude'),
            $request->input('longitude')
        );

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }
}
