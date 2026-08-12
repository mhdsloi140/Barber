<?php
// app/Http/Controllers/API/Customer/SalonController.php

namespace App\Http\Controllers\API\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\SalonSearchRequest;
use App\Services\Customer\SalonService;
use Illuminate\Http\Request;

class SalonController extends Controller
{
    public function __construct(
        private SalonService $salonService
    ) {}

    /**
     * عرض جميع الصالونات
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

   
    public function show($id, Request $request)
    {
        $result = $this->salonService->getSalon(
            $id,
            $request->input('latitude'),
            $request->input('longitude'),
            $request->input('date'),
            $request->input('barber_id'),    // فقط إذا تم إرساله
            $request->input('service_id')
        );

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }
}
