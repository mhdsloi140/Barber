<?php

namespace App\Services\Admin\Auth;

use App\Models\User;
use App\Services\ultraMessage\UltraMsgService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class AuthAdminServics
{
    protected UltraMsgService $whatsappService;
    protected const RESET_PASSWORD_PREFIX = 'admin_password_reset_';
    protected const OTP_EXPIRY_MINUTES = 3; // ✅ مدة صلاحية OTP بالدقائق

    public function __construct(UltraMsgService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }

    public function login($data)
    {
        $user = User::where('phone', $data['phone'])->first();

        if (!$user) {
            Log::warning('محاولة دخول برقم هاتف غير موجود', ['phone' => $data['phone']]);
            return [
                'status' => false,
                'message' => 'رقم الهاتف غير موجود',
                'code' => 'phone_not_found'
            ];
        }

        if ($user->role !== 'admin' && $user->role !== 'مدير') {
            Log::warning('محاولة دخول من مستخدم بدون صلاحية admin', [
                'phone' => $data['phone'],
                'role' => $user->role ?? 'غير محدد'
            ]);
            return [
                'status' => false,
                'message' => 'ليس لديك صلاحية للدخول إلى لوحة التحكم',
                'code' => 'not_admin'
            ];
        }

        if (!Hash::check($data['password'], $user->password)) {
            return [
                'status' => false,
                'message' => 'خطأ في كلمة المرور أو رقم الهاتف',
                'code' => 'invalid_password'
            ];
        }

        return [
            'status' => true,
            'message' => 'تم تسجيل الدخول بنجاح',
            'user' => $user,
            'code' => 'success'
        ];
    }

    /**
     * طلب إعادة تعيين كلمة المرور (إرسال OTP)
     */
    public function forgotPassword(string $phone): array
    {
        try {
            $user = User::where('phone', $phone)->first();

            if (!$user) {
                return [
                    'status' => false,
                    'message' => 'لا يوجد حساب مرتبط بهذا الرقم',
                    'code' => 'user_not_found'
                ];
            }

            if ($user->role !== 'admin' && $user->role !== 'مدير') {
                return [
                    'status' => false,
                    'message' => 'هذا الحساب ليس مديراً',
                    'code' => 'not_admin'
                ];
            }

            $otpCode = sprintf("%06d", rand(0, 999999));

            $cacheKey = self::RESET_PASSWORD_PREFIX . $user->id;
            Cache::put($cacheKey, [
                'otp' => $otpCode,
                'attempts' => 0,
                'created_at' => now(),
            ], now()->addMinutes(self::OTP_EXPIRY_MINUTES));


            $message = " *إعادة تعيين كلمة المرور* \n\n"
                     . "مرحباً {$user->name}،\n\n"
                     . "لقد طلبت إعادة تعيين كلمة المرور الخاصة بحسابك في لوحة تحكم نعيما.\n\n"
                     . " *رمز التحقق الخاص بك:*\n"
                     . "*{$otpCode}*\n\n"
                     . " هذا الرمز صالح لمدة " . self::OTP_EXPIRY_MINUTES . " دقائق فقط.\n"
                     . " لا تشارك هذا الرمز مع أي شخص.\n\n"
                     . "إذا لم تطلب هذا الرمز، يمكنك تجاهل هذه الرسالة.";

            $result = $this->whatsappService->sendMessage($phone, $message, 5);

            if (!$result['success']) {
                return [
                    'status' => false,
                    'message' => 'فشل إرسال رمز التحقق، يرجى المحاولة لاحقاً',
                    'code' => 'send_failed'
                ];
            }

            Log::info('Password reset OTP sent to admin', [
                'user_id' => $user->id,
                'phone' => $phone,
            ]);

            return [
                'status' => true,
                'message' => 'تم إرسال رمز التحقق إلى رقم واتساب الخاص بك',
                'data' => [
                    'user_id' => $user->id,
                    'phone' => $user->phone,
                    'expires_in' => self::OTP_EXPIRY_MINUTES,
                ],
                'code' => 'success'
            ];

        } catch (\Exception $e) {
            Log::error('Admin forgot password error: ' . $e->getMessage());
            return [
                'status' => false,
                'message' => 'حدث خطأ أثناء إرسال رمز التحقق',
                'code' => 'error'
            ];
        }
    }

    /**
     * التحقق من OTP وإعادة تعيين كلمة المرور
     */
    public function resetPassword(string $phone, string $otpCode, string $newPassword): array
    {
        try {
            $user = User::where('phone', $phone)->first();

            if (!$user) {
                return [
                    'status' => false,
                    'message' => 'المستخدم غير موجود',
                    'code' => 'user_not_found'
                ];
            }

            if ($user->role !== 'admin' && $user->role !== 'مدير') {
                return [
                    'status' => false,
                    'message' => 'هذا الحساب ليس مديراً',
                    'code' => 'not_admin'
                ];
            }

            $cacheKey = self::RESET_PASSWORD_PREFIX . $user->id;
            $resetData = Cache::get($cacheKey);

            if (!$resetData) {
                return [
                    'status' => false,
                    'message' => 'رمز التحقق منتهي الصلاحية أو غير موجود، يرجى طلب رمز جديد',
                    'code' => 'expired'
                ];
            }

            if ($resetData['attempts'] >= 3) {
                Cache::forget($cacheKey);
                return [
                    'status' => false,
                    'message' => 'تم تجاوز عدد المحاولات المسموح به، يرجى طلب رمز جديد',
                    'code' => 'max_attempts'
                ];
            }

            if ($resetData['otp'] !== $otpCode) {
                $resetData['attempts']++;
                Cache::put($cacheKey, $resetData, now()->diffInSeconds($resetData['created_at']->addMinutes(self::OTP_EXPIRY_MINUTES)));

                $remainingAttempts = 3 - $resetData['attempts'];
                return [
                    'status' => false,
                    'message' => "رمز التحقق غير صحيح. محاولات متبقية: {$remainingAttempts}",
                    'data' => ['remaining_attempts' => $remainingAttempts],
                    'code' => 'invalid_otp'
                ];
            }

            $user->update([
                'password' => Hash::make($newPassword),
            ]);

            Cache::forget($cacheKey);

            $this->sendPasswordChangedConfirmation($user);

            Log::info('Admin password reset successfully', [
                'user_id' => $user->id,
                'phone' => $phone,
            ]);

            return [
                'status' => true,
                'message' => 'تم إعادة تعيين كلمة المرور بنجاح',
                'code' => 'success'
            ];

        } catch (\Exception $e) {
            Log::error('Admin reset password error: ' . $e->getMessage());
            return [
                'status' => false,
                'message' => 'حدث خطأ أثناء إعادة تعيين كلمة المرور',
                'code' => 'error'
            ];
        }
    }

    /**
     * إرسال رسالة تأكيد تغيير كلمة المرور
     */
    private function sendPasswordChangedConfirmation(User $user): void
    {
        try {
            $message = "✅ *تم تغيير كلمة المرور بنجاح* ✅\n\n"
                     . "مرحباً {$user->name}،\n\n"
                     . "تم تغيير كلمة المرور الخاصة بحسابك في لوحة تحكم نعيما بنجاح.\n\n"
                     . "إذا لم تقم بذلك، يرجى التواصل مع الدعم الفني فوراً.\n\n"
                     . "يمكنك الآن تسجيل الدخول باستخدام كلمة المرور الجديدة.";

            $this->whatsappService->sendMessage($user->phone, $message, 5);
        } catch (\Exception $e) {
            Log::error('Failed to send password changed confirmation: ' . $e->getMessage());
        }
    }

    /**
     * التحقق من صلاحية رمز إعادة التعيين
     */
    public function checkResetOTPStatus(int $userId): array
    {
        try {
            $user = User::find($userId);

            if (!$user) {
                return [
                    'status' => false,
                    'message' => 'المستخدم غير موجود',
                    'code' => 'user_not_found'
                ];
            }

            $cacheKey = self::RESET_PASSWORD_PREFIX . $userId;
            $resetData = Cache::get($cacheKey);

            if (!$resetData) {
                return [
                    'status' => false,
                    'message' => 'لا يوجد رمز فعال لإعادة التعيين',
                    'code' => 'no_active_code'
                ];
            }

            $remainingAttempts = 3 - $resetData['attempts'];
            $remainingSeconds = now()->diffInSeconds($resetData['created_at']->addMinutes(self::OTP_EXPIRY_MINUTES));

            return [
                'status' => true,
                'message' => 'رمز إعادة التعيين فعال',
                'data' => [
                    'has_active_code' => true,
                    'remaining_attempts' => $remainingAttempts,
                    'remaining_seconds' => $remainingSeconds,
                    'expires_at' => $resetData['created_at']->addMinutes(self::OTP_EXPIRY_MINUTES)->toISOString(),
                ],
                'code' => 'success'
            ];

        } catch (\Exception $e) {
            Log::error('Check reset OTP status error: ' . $e->getMessage());
            return [
                'status' => false,
                'message' => 'حدث خطأ',
                'code' => 'error'
            ];
        }
    }
}
