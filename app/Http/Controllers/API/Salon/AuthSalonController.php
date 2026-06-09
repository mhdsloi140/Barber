<?php

namespace App\Http\Controllers\API\Salon;

use App\Http\Controllers\Controller;
use App\Http\Requests\Salon\SendOtpRequest;
use App\Http\Requests\Salon\ResendOtpRequest;
use App\Http\Requests\Salon\VerifyOtpRequest;
use App\Services\Salon\RegisterService;
use Illuminate\Http\Request;

class AuthSalonController extends Controller
{
    public function __construct(
        private RegisterService $registerService,
    ) {}

    /**
     * الخطوة 1: إرسال كود التحقق لرقم الهاتف
     * POST /api/auth/register/salon-owner/send-otp
     */
    public function sendOtp(SendOtpRequest $request)
    {
        $result = $this->registerService->sendVerificationCode($request->validated());

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }

    /**
     * الخطوة 2: إعادة إرسال كود التحقق
     * POST /api/auth/register/salon-owner/resend-otp
     */
    public function resendOtp(ResendOtpRequest $request)
    {
        $result = $this->registerService->resendVerificationCode($request->phone);

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }

    /**
     * الخطوة 3: التحقق من الكود وإنشاء الحساب (غير مفعل - ينتظر موافقة المدير)
     * POST /api/auth/register/salon-owner/verify
     */
    public function verifyAndCreate(VerifyOtpRequest $request)
    {
        $result = $this->registerService->verifyCodeAndCreate(
            $request->phone,
            $request->code
        );

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }

    /**
     * جلب طلبات التسجيل المعلقة (للمدير فقط)
     * GET /api/admin/pending-registrations
     */
    public function getPendingRegistrations()
    {
        $result = $this->registerService->getPendingRegistrations();

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }

    /**
     * تفعيل حساب صاحب صالون (للمدير فقط)
     * POST /api/admin/approve-saloon/{userId}
     */
    public function approveSalonOwner(Request $request, int $userId)
    {
        $result = $this->registerService->approveSalonOwner($userId, auth()->id());

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }

    /**
     * رفض طلب تسجيل صاحب صالون (للمدير فقط)
     * POST /api/admin/reject-saloon/{userId}
     */
    public function rejectSalonOwner(Request $request, int $userId)
    {
        $result = $this->registerService->rejectSalonOwner(
            $userId,
            auth()->id(),
            $request->input('reason')
        );

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }
}