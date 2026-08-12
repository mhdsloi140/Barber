<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\VerifyOTPRequest;
use App\Services\AuthAllUserServices;
use Illuminate\Http\Request;
use App\Models\User;

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

    public function resetPassword(ResetPasswordRequest $request)
    {
        $result = $this->authService->resetPassword(
            $request->phone,
            $request->code,
            $request->password
        );
        return response()->json(
            $result->toArray(),
            $result->success ? 200 : 400
        );
    }

    public function register(RegisterRequest $request)
    {
        $result = $this->authService->register($request);
        return response()->json(
            $result->toArray(),
            $result->success ? 201 : 422
        );
    }

    /**
     * التحقق من OTP وتفعيل الحساب (باستخدام رقم الهاتف)
     */
    public function verifyOTP(VerifyOTPRequest $request)
    {
        $result = $this->authService->verifyOTPAndActivate(
            $request->phone,  
            $request->code
        );

        return response()->json(
            $result->toArray(),
            $result->success ? 200 : 400
        );
    }

    public function forgotPassword(ForgotPasswordRequest $request)
    {
        $result = $this->authService->forgotPassword($request->phone);
        return response()->json(
            $result->toArray(),
            $result->success ? 200 : 400
        );
    }


    public function resendOTP(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|exists:users,phone',
        ]);

        $result = $this->authService->resendOTP($request->phone);

        return response()->json(
            $result->toArray(),
            $result->success ? 200 : 400
        );
    }


    public function checkOTPStatus(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|exists:users,phone',
        ]);

        $result = $this->authService->checkOTPStatus($request->phone);

        return response()->json(
            $result->toArray(),
            $result->success ? 200 : 400
        );
    }

    public function logout(Request $request)
    {
        $result = $this->authService->logout($request->user());
        return response()->json(
            $result->toArray(),
            $result->success ? 200 : 400
        );
    }

    public function logoutFromAllDevices(Request $request)
    {
        $result = $this->authService->logoutFromAllDevices($request->user());
        return response()->json(
            $result->toArray(),
            $result->success ? 200 : 400
        );
    }

    public function me(Request $request)
    {
        $result = $this->authService->getCurrentUser($request->user());
        return response()->json(
            $result->toArray(),
            $result->success ? 200 : 404
        );
    }

    public function refresh(Request $request)
    {
        $result = $this->authService->refreshToken($request->user());
        return response()->json(
            $result->toArray(),
            $result->success ? 200 : 400
        );
    }
}
