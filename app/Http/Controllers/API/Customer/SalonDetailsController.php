<?php
// app/Http/Controllers/API/Customer/SalonDetailsController.php

namespace App\Http\Controllers\API\Customer;

use App\Http\Controllers\Controller;
use App\Services\Customer\SalonDetailsService;
use Illuminate\Http\Request;

class SalonDetailsController extends Controller
{
    public function __construct(
        private SalonDetailsService $salonDetailsService
    ) {}

    /**
     * عرض جميع بيانات الصالون (الحلاقين، الخدمات، أوقات العمل)
     * GET /api/customer/salons/{id}/details
     */
    public function show($id)
    {
        $result = $this->salonDetailsService->getSalonDetails((int) $id);


;        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }
}
