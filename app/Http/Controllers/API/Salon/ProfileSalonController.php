<?php
// app/Http/Controllers/API/Salon/ProfileSalonController.php

namespace App\Http\Controllers\API\Salon;

use App\Http\Controllers\Controller;
use App\Http\Requests\Salon\UpdateSalonRequest;
use App\Services\Salon\UpdateSalonService;
use Illuminate\Http\Request;

class ProfileSalonController extends Controller
{
    public function __construct(
        private UpdateSalonService $updateSalonService
    ) {}

    /**
     * عرض بيانات الصالون الشخصية مع التقييمات
     * GET /api/salon/profile
     */
    public function show()
    {
        $result = $this->updateSalonService->showSalonProfile();

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }

    /**
     * تحديث بيانات الصالون الشخصية
     * PUT /api/salon/profile
     */
    public function update(UpdateSalonRequest $request)
    {
        $result = $this->updateSalonService->updateSalon($request->validated());

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }
}
