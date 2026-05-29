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

    /**
     * تسجيل جديد (حفظ البيانات + إرسال OTP)
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
     *  التحقق من OTP وتفعيل الحساب
     */
    public function verifyOTP(VerifyOTPRequest $request)
    {
        $result = $this->authService->verifyOTPAndActivate(
            $request->user_id,
            $request->code
        );

        return response()->json(
            $result->toArray(),
            $result->success ? 200 : 400
        );
    }

    /**
     *  إعادة إرسال OTP
     */
    public function resendOTP(ResendOTPRequest $request)
    {
        $result = $this->authService->resendOTP($request->user_id);

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

    /**
     *  التحقق من صلاحية رمز OTP (دون تفعيل)
     */
    public function checkOTPStatus(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $result = $this->authService->checkOTPStatus($request->user_id);

        return response()->json(
            $result->toArray(),
            $result->success ? 200 : 400
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
