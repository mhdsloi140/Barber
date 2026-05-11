<?php
// app/Http/Controllers/API/Barber/BarberProfileController.php

namespace App\Http\Controllers\API\Barber;

use App\Http\Controllers\Controller;
use App\Http\Requests\Barber\UpdateBarberProfileRequest;
use App\Services\Barber\BarberProfileService;
use Illuminate\Http\Request;

class BarberProfileController extends Controller
{
    public function __construct(
        private BarberProfileService $profileService
    ) {}

    /**
     * عرض الملف الشخصي للحلاق
     * GET /api/barber/profile
     */
    public function show()
    {
        $result = $this->profileService->getProfile(auth()->user());

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }

    /**
     * تحديث الملف الشخصي للحلاق
     * PUT /api/barber/profile
     */
    public function update(UpdateBarberProfileRequest $request)
    {
        $data = $request->validated();

        if ($request->hasFile('avatar')) {
            $data['avatar'] = $request->file('avatar');
        }

        $result = $this->profileService->updateProfile(auth()->user(), $data);

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }
}
