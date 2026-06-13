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

    public function __construct(UltraMsgService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }

    public function login($data)
    {
        // 1. البحث عن المستخدم برقم الهاتف
        $user = User::where('phone', $data['phone'])->first();

        // 2. التحقق: هل رقم الهاتف موجود؟
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

        // 4. التحقق: هل كلمة المرور صحيحة؟
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

            // التحقق من أن المستخدم مدير
            if ($user->role !== 'admin' && $user->role !== 'مدير') {
                return [
                    'status' => false,
                    'message' => 'هذا الحساب ليس مديراً',
                    'code' => 'not_admin'
                ];
            }

            // توليد OTP عشوائي (6 أرقام)
            $otpCode = sprintf("%06d", rand(0, 999999));

            // تخزين OTP في Cache (مدة 10 دقائق)
            $cacheKey = self::RESET_PASSWORD_PREFIX . $user->id;
            Cache::put($cacheKey, [
                'otp' => $otpCode,
                'attempts' => 0,
                'created_at' => now(),
            ], now()->addMinutes(10));

            // إرسال OTP عبر WhatsApp
            $message = " *إعادة تعيين كلمة المرور* 🔐\n\n"
                     . "مرحباً {$user->name}،\n\n"
                     . "لقد طلبت إعادة تعيين كلمة المرور الخاصة بحسابك في لوحة تحكم نعيما.\n\n"
                     . " *رمز التحقق الخاص بك:*\n"
                     . "*{$otpCode}*\n\n"
                     . " هذا الرمز صالح لمدة 3 دقائق فقط.\n"
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
                    'expires_in' => 10,
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

            // التحقق من أن المستخدم مدير
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

            // التحقق من عدد المحاولات
            if ($resetData['attempts'] >= 3) {
                Cache::forget($cacheKey);
                return [
                    'status' => false,
                    'message' => 'تم تجاوز عدد المحاولات المسموح به، يرجى طلب رمز جديد',
                    'code' => 'max_attempts'
                ];
            }

            // التحقق من صحة الرمز
            if ($resetData['otp'] !== $otpCode) {
                $resetData['attempts']++;
                Cache::put($cacheKey, $resetData, now()->diffInSeconds($resetData['created_at']->addMinutes(10)));

                $remainingAttempts = 3 - $resetData['attempts'];
                return [
                    'status' => false,
                    'message' => "رمز التحقق غير صحيح. محاولات متبقية: {$remainingAttempts}",
                    'data' => ['remaining_attempts' => $remainingAttempts],
                    'code' => 'invalid_otp'
                ];
            }

            // تحديث كلمة المرور
            $user->update([
                'password' => Hash::make($newPassword),
            ]);

            // تنظيف الـ Cache
            Cache::forget($cacheKey);

            // إرسال رسالة تأكيد عبر WhatsApp
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
            $remainingSeconds = now()->diffInSeconds($resetData['created_at']->addMinutes(10));

            return [
                'status' => true,
                'message' => 'رمز إعادة التعيين فعال',
                'data' => [
                    'has_active_code' => true,
                    'remaining_attempts' => $remainingAttempts,
                    'remaining_seconds' => $remainingSeconds,
                    'expires_at' => $resetData['created_at']->addMinutes(10)->toISOString(),
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
