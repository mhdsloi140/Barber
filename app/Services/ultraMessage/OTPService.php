<?php

namespace App\Services\ultraMessage;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class OTPService
{
    protected int $otpLength;
    protected int $expiresInMinutes;
    protected int $maxAttempts;
    protected UltraMsgService $whatsappService;

    public function __construct(UltraMsgService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
        $this->otpLength = config('otp.length', 6);
        $this->expiresInMinutes = config('otp.expires_in', 10);
        $this->maxAttempts = config('otp.max_attempts', 3);
    }

    /**
     * توليد رمز OTP عشوائي
     */
    public function generateOTP(): string
    {
        return str_pad(random_int(0, 10 ** $this->otpLength - 1), $this->otpLength, '0', STR_PAD_LEFT);
    }

    /**
     * تخزين OTP للمستخدم
     */
    public function storeOTP(int $userId, string $otpCode): void
    {
        $cacheKey = "otp_user_{$userId}";

        Cache::put($cacheKey, [
            'otp' => $otpCode,
            'attempts' => 0,
            'created_at' => now(),
        ], now()->addMinutes($this->expiresInMinutes));

        Log::debug('OTP stored', ['user_id' => $userId, 'expires_in' => $this->expiresInMinutes]);
    }

    /**
     * إرسال OTP عبر واتساب
     */
    public function sendOTP(string $phone, int $userId): array
    {
        // توليد رمز جديد
        $otpCode = $this->generateOTP();

        // تخزين الرمز
        $this->storeOTP($userId, $otpCode);

        // إرسال عبر واتساب
        $result = $this->whatsappService->sendOTP($phone, $otpCode, $this->expiresInMinutes);

        if (!$result['success']) {
            Log::error('Failed to send OTP', [
                'user_id' => $userId,
                'phone' => $phone,
                'error' => $result['error'] ?? 'unknown'
            ]);
        }

        return [
            'success' => $result['success'],
            'otp' => $otpCode, // يمكن إزالته في الإنتاج (للتجربة فقط)
            'expires_in' => $this->expiresInMinutes * 60,
            'error' => $result['error'] ?? null
        ];
    }

    /**
     * التحقق من صحة OTP
     */
    public function verifyOTP(int $userId, string $otpCode): array
    {
        $cacheKey = "otp_user_{$userId}";
        $otpData = Cache::get($cacheKey);

        if (!$otpData) {
            return [
                'success' => false,
                'error' => 'رمز التحقق منتهي الصلاحية أو غير موجود',
                'code' => 'EXPIRED'
            ];
        }

        // التحقق من عدد المحاولات
        if ($otpData['attempts'] >= $this->maxAttempts) {
            Cache::forget($cacheKey);
            return [
                'success' => false,
                'error' => 'تم تجاوز عدد المحاولات المسموح به، يرجى طلب رمز جديد',
                'code' => 'MAX_ATTEMPTS'
            ];
        }

        // التحقق من صحة الرمز
        if ($otpData['otp'] !== $otpCode) {
            $otpData['attempts']++;
            Cache::put($cacheKey, $otpData, now()->diffInSeconds($otpData['created_at']->addMinutes($this->expiresInMinutes)));

            $remainingAttempts = $this->maxAttempts - $otpData['attempts'];

            return [
                'success' => false,
                'error' => "رمز التحقق غير صحيح",
                'remaining_attempts' => $remainingAttempts,
                'code' => 'INVALID'
            ];
        }

        // رمز صحيح - ننظف الـ Cache
        Cache::forget($cacheKey);

        return [
            'success' => true,
            'code' => 'VERIFIED'
        ];
    }

    /**
     * إعادة إرسال OTP
     */
    public function resendOTP(string $phone, int $userId): array
    {
        // حذف الـ OTP القديم
        $cacheKey = "otp_user_{$userId}";
        Cache::forget($cacheKey);

        // إرسال OTP جديد
        return $this->sendOTP($phone, $userId);
    }
}
