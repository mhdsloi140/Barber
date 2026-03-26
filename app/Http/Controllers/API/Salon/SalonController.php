<?php
// app/Http/Controllers/API/Salon/SalonController.php

namespace App\Http\Controllers\API\Salon;

use App\Http\Controllers\Controller;
use App\Http\Requests\SalonRequest;
use App\Services\Salon\SalonService;
use Illuminate\Http\Request;

class SalonController extends Controller
{
    public function __construct(
        private SalonService $salonService
    ) {}

    /**
     * عرض بيانات الصالون
     * GET /api/salon
     */
    public function show()
    {
        $result = $this->salonService->getSalon(auth()->user());

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }

    /**
     * تحديث بيانات الصالون
     * PUT /api/salon
     */
    public function update(SalonRequest $request)
    {
        if (!$request->hasDataToUpdate()) {
            return response()->json([
                'success' => false,
                'message' => 'لا توجد بيانات للتحديث'
            ], 400);
        }

        $result = $this->salonService->updateSalon(
            auth()->user(),
            $request->getUpdateData()
        );

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }

    /**
     * عرض إحصائيات الصالون
     * GET /api/salon/stats
     */
    public function stats()
    {
        $result = $this->salonService->getSalonStats(auth()->user());

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }

 
    public function toggleStatus()
    {
        $result = $this->salonService->toggleStatus(auth()->user());

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }
}
