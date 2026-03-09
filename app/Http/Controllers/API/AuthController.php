<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthAllUserServices;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private AuthAllUserServices $authService
    ) {}

    /**
     * تسجيل الدخول
     */
    public function login(LoginRequest $request)
    {
        $result = $this->authService->login($request->validated());

        return response()->json(
            $result->toArray(),
            $result->success ? 200 : 401
        );
    }

    /**
     * تسجيل جديد
     */
    public function register(RegisterRequest $request)
    {
        $result = $this->authService->register($request);

        return response()->json(
            $result->toArray(),
            $result->success ? 201 : 422
        );
    }

    /**
     * تسجيل الخروج
     */
    public function logout(Request $request)
    {
        $result = $this->authService->logout($request->user());

        return response()->json(
            $result->toArray(),
            $result->success ? 200 : 400
        );
    }

    /**
     * تسجيل الخروج من جميع الأجهزة
     */
    public function logoutFromAllDevices(Request $request)
    {
        $result = $this->authService->logoutFromAllDevices($request->user());

        return response()->json(
            $result->toArray(),
            $result->success ? 200 : 400
        );
    }

    /**
     * الحصول على المستخدم الحالي
     */
    public function me(Request $request)
    {
        $result = $this->authService->getCurrentUser($request->user());

        return response()->json(
            $result->toArray(),
            $result->success ? 200 : 404
        );
    }

    /**
     * تحديث التوكن
     */
    public function refresh(Request $request)
    {
        $result = $this->authService->refreshToken($request->user());

        return response()->json(
            $result->toArray(),
            $result->success ? 200 : 400
        );
    }
}
