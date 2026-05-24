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

    /**
     * عرض صالون محدد
     * GET /api/customer/salons/{id}
     *
     * @queryParam barber_id int optional - معرف الحلاق لجلب أوقات الفراغ
     * @queryParam date string optional - التاريخ المطلوب (Y-m-d)
     * @queryParam service_id int optional - معرف الخدمة
     * @queryParam latitude float optional - خط العرض لحساب المسافة
     * @queryParam longitude float optional - خط الطول لحساب المسافة
     */
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
