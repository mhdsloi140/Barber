<?php
// app/Http/Controllers/API/Salon/AuthController.php

namespace App\Http\Controllers\API\Salon;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterSalonOwnerRequest;
use App\Services\Salon\RegisterService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private RegisterService $registerService,
    ) {}

    /**
     * تسجيل صاحب صالون جديد
     * POST /api/auth/register/salon-owner
     */
    public function registerSalonOwner(RegisterSalonOwnerRequest $request)
    {
        //  صور الصالون
        $images = $request->hasFile('images') ? $request->file('images') : null;

        //  الصورة الشخصية
        $avatar = $request->hasFile('avatar') ? $request->file('avatar') : null;

        $result = $this->registerService->registerSalonOwner(
            $request->validated(),
            $images,
            $avatar
        );

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }
}
