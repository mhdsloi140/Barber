<?php
// app/Services/AuthAllUserServices.php

namespace App\Services;

use App\Models\User;
use App\Models\Salon;
use App\Http\Requests\RegisterRequest;
use App\Services\AuthResult;
use App\Services\ultraMessage\OTPService;
use App\Services\ultraMessage\UltraMsgService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;

use Illuminate\Support\Facades\Cache;

class AuthAllUserServices
{
    protected UltraMsgService $whatsappService;
    protected OTPService $otpService;

    public function __construct(UltraMsgService $whatsappService, OTPService $otpService)
    {
        $this->whatsappService = $whatsappService;
        $this->otpService = $otpService;
    }
    /**
 *  إرسال OTP لمستخدم (للاختبار)
 */
public function sendOTPToUser(int $userId): array
{
    try {
        $user = User::find($userId);

        if (!$user) {
            return ['success' => false, 'error' => 'User not found'];
        }

        return $this->otpService->sendOTP($user->phone, $userId);

    } catch (\Exception $e) {
        Log::error('Send OTP error: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 *  التحقق من صلاحية OTP (بدون تفعيل)
 */
public function checkOTPStatus(int $userId): AuthResult
{
    try {
        $user = User::find($userId);

        if (!$user) {
            return AuthResult::error('المستخدم غير موجود', null, 404);
        }

        $cacheKey = "otp_user_{$userId}";
        $otpData = Cache::get($cacheKey);

        if (!$otpData) {
            return AuthResult::error('لا يوجد رمز فعال', null, 404);
        }

        $remainingAttempts = 3 - $otpData['attempts'];
        $remainingSeconds = now()->diffInSeconds($otpData['created_at']->addMinutes(10));

        return AuthResult::success('الرمز فعال', [
            'has_active_otp' => true,
            'remaining_attempts' => $remainingAttempts,
            'remaining_seconds' => $remainingSeconds,
            'expires_at' => $otpData['created_at']->addMinutes(10)->toISOString(),
        ]);

    } catch (\Exception $e) {
        Log::error('Check OTP status error: ' . $e->getMessage());
        return AuthResult::error('حدث خطأ', null, 500);
    }
}

    /**
     * تسجيل الدخول
     */
    public function login(array $credentials): AuthResult
    {
        try {
            $user = User::where('phone', $credentials['phone'])->first();

            if (!$user || !Hash::check($credentials['password'], $user->password)) {
                return AuthResult::error(
                    'رقم الهاتف أو كلمة المرور غير صحيحة',
                    null,
                    401
                );
            }

            if (!$user->is_active) {
                return AuthResult::error(
                    'الحساب غير مفعل. يرجى تفعيل الحساب باستخدام رمز التحقق المرسل إلى واتساب',
                    ['user_id' => $user->id, 'requires_verification' => true],
                    403
                );
            }

            $this->loadUserRelations($user);
            $token = $this->generateToken($user);
            $userData = $this->formatUserData($user, $token);

            Log::info('User logged in', ['user_id' => $user->id]);

            return AuthResult::success('تم تسجيل الدخول بنجاح', $userData);

        } catch (\Exception $e) {
            Log::error('Login error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء تسجيل الدخول', config('app.debug') ? $e->getMessage() : null, 500);
        }
    }

    /**
     * تسجيل مستخدم جديد (مع إرسال OTP)
     */
    public function register(RegisterRequest $request): AuthResult
    {
        try {
            // التحقق من عدم وجود الرقم مسبقاً
            $existingUser = User::where('phone', $request->phone)->first();
            if ($existingUser) {
                return AuthResult::error('رقم الهاتف مسجل مسبقاً', null, 422);
            }

            return DB::transaction(function () use ($request) {

                $userType = $this->determineUserType($request);

                // إنشاء المستخدم (غير مفعل)
                $user = $this->createUser($request, $userType);

                // رفع الصورة الشخصية إذا وجدت
                if ($request->hasFile('avatar')) {
                    $this->uploadAvatar($user, $request->file('avatar'));
                }

                // إذا كان صاحب صالون، أنشئ الصالون
                if ($userType === 'salon_owner') {
                    $this->createSalonForOwner($request, $user);
                }

                // تعيين الدور
                $this->assignRole($user, $userType);

                // إرسال OTP عبر واتساب
                $otpResult = $this->otpService->sendOTP($user->phone, $user->id);

                if (!$otpResult['success']) {
                    // إذا فشل إرسال OTP، نحذف المستخدم
                    $user->delete();
                    return AuthResult::error('فشل إرسال رمز التحقق، يرجى المحاولة لاحقاً', null, 500);
                }

                Log::info('User registered, OTP sent', [
                    'user_id' => $user->id,
                    'phone' => $user->phone,
                    // 'otp' => $otpResult['otp'] // أزل التعليق للتجربة فقط
                ]);

                // إرجاع نجاح مع طلب التحقق
                return AuthResult::success('تم إنشاء الحساب بنجاح. تم إرسال رمز التحقق إلى واتساب.', [
                    'user_id' => $user->id,
                    'phone' => $user->phone,
                    'requires_verification' => true,
                    'expires_in' => $otpResult['expires_in'],
                ], 201);
            });

        } catch (\Illuminate\Validation\ValidationException $e) {
            return AuthResult::error('خطأ في البيانات المدخلة', $e->errors(), 422);
        } catch (\Exception $e) {
            Log::error('Registration error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء التسجيل', config('app.debug') ? $e->getMessage() : null, 500);
        }
    }

    /**
     * التحقق من OTP وتفعيل الحساب
     */
    public function verifyOTPAndActivate(int $userId, string $otpCode): AuthResult
    {
        try {
            $user = User::find($userId);

            if (!$user) {
                return AuthResult::error('المستخدم غير موجود', null, 404);
            }

            // إذا كان الحساب مفعلاً بالفعل
            if ($user->is_active) {
                return AuthResult::error('الحساب مفعل بالفعل', null, 400);
            }

            // التحقق من OTP
            $verification = $this->otpService->verifyOTP($userId, $otpCode);

            if (!$verification['success']) {
                $data = [];
                if (isset($verification['remaining_attempts'])) {
                    $data['remaining_attempts'] = $verification['remaining_attempts'];
                }
                return AuthResult::error($verification['error'], $data, 400);
            }

            // تفعيل الحساب
            $user->update(['is_active' => true]);

            // تحميل العلاقات
            $this->loadUserRelations($user);

            // إنشاء توكن
            $token = $this->generateToken($user);

            // تنسيق بيانات المستخدم
            $userData = $this->formatUserData($user, $token);

            Log::info('User activated via OTP', ['user_id' => $user->id]);

            return AuthResult::success('تم تفعيل الحساب بنجاح', $userData, 200);

        } catch (\Exception $e) {
            Log::error('OTP verification error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء التحقق', config('app.debug') ? $e->getMessage() : null, 500);
        }
    }

    /**
     * إعادة إرسال OTP
     */
    public function resendOTP(int $userId): AuthResult
    {
        try {
            $user = User::find($userId);

            if (!$user) {
                return AuthResult::error('المستخدم غير موجود', null, 404);
            }

            // إذا كان الحساب مفعلاً
            if ($user->is_active) {
                return AuthResult::error('الحساب مفعل بالفعل', null, 400);
            }

            // إعادة إرسال OTP
            $result = $this->otpService->resendOTP($user->phone, $userId);

            if ($result['success']) {
                Log::info('OTP resent', ['user_id' => $userId]);
                return AuthResult::success('تم إعادة إرسال رمز التحقق بنجاح', [
                    'expires_in' => $result['expires_in']
                ]);
            }

            return AuthResult::error('فشل إعادة إرسال رمز التحقق', null, 500);

        } catch (\Exception $e) {
            Log::error('Resend OTP error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء إعادة الإرسال', null, 500);
        }
    }

    /**
     * تسجيل الخروج
     */
    public function logout(?User $user): AuthResult
    {
        try {
            if (!$user) {
                return AuthResult::error('المستخدم غير موجود', null, 404);
            }

            $user->currentAccessToken()->delete();

            Log::info('User logged out', ['user_id' => $user->id]);

            return AuthResult::success('تم تسجيل الخروج بنجاح');

        } catch (\Exception $e) {
            Log::error('Logout error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء تسجيل الخروج', config('app.debug') ? $e->getMessage() : null, 500);
        }
    }

    /**
     * تسجيل الخروج من جميع الأجهزة
     */
    public function logoutFromAllDevices(?User $user): AuthResult
    {
        try {
            if (!$user) {
                return AuthResult::error('المستخدم غير موجود', null, 404);
            }

            $user->tokens()->delete();

            Log::info('User logged out from all devices', ['user_id' => $user->id]);

            return AuthResult::success('تم تسجيل الخروج من جميع الأجهزة بنجاح');

        } catch (\Exception $e) {
            Log::error('Logout from all devices error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء تسجيل الخروج', config('app.debug') ? $e->getMessage() : null, 500);
        }
    }

    /**
     * الحصول على المستخدم الحالي
     */
    public function getCurrentUser(?User $user): AuthResult
    {
        try {
            if (!$user) {
                return AuthResult::error('المستخدم غير موجود', null, 404);
            }

            $this->loadUserRelations($user);

            $token = $user->currentAccessToken()?->plainTextToken;
            $userData = $this->formatUserData($user, $token);

            return AuthResult::success('تم جلب البيانات بنجاح', $userData);

        } catch (\Exception $e) {
            Log::error('Get current user error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب البيانات', config('app.debug') ? $e->getMessage() : null, 500);
        }
    }

    /**
     * تحديث التوكن
     */
    public function refreshToken(?User $user): AuthResult
    {
        try {
            if (!$user) {
                return AuthResult::error('المستخدم غير موجود', null, 404);
            }

            // حذف التوكن الحالي
            $user->currentAccessToken()->delete();

            // إنشاء توكن جديد
            $token = $this->generateToken($user);

            $userData = $this->formatUserData($user, $token);

            Log::info('Token refreshed', ['user_id' => $user->id]);

            return AuthResult::success('تم تحديث التوكن بنجاح', $userData);

        } catch (\Exception $e) {
            Log::error('Refresh token error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء تحديث التوكن', config('app.debug') ? $e->getMessage() : null, 500);
        }
    }

    /**
     * رفع الصورة الشخصية
     */
    private function uploadAvatar(User $user, UploadedFile $avatar): void
    {
        try {
            $user->addMedia($avatar)
                ->usingFileName($this->generateAvatarFileName($avatar))
                ->toMediaCollection('avatar');

            Log::info('Avatar uploaded for user', ['user_id' => $user->id]);
        } catch (\Exception $e) {
            Log::error('Avatar upload failed: ' . $e->getMessage());
        }
    }

    /**
     * توليد اسم فريد للصورة الشخصية
     */
    private function generateAvatarFileName(UploadedFile $file): string
    {
        return 'avatar_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
    }

    /**
     * تنسيق بيانات المستخدم
     */
    private function formatUserData(User $user, ?string $token = null): array
    {
        $roles = $user->getRoleNames()->toArray();
        $primaryRole = !empty($roles) ? $roles[0] : ($user->role ?? 'customer');

        $data = [
            'id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
            'roles' => $roles,
            'is_active' => $user->is_active,
            'avatar' => $user->getAvatarUrlAttribute(),
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];

        if ($token) {
            $data['token'] = $token;
            $data['token_type'] = 'Bearer';
        }

        // إذا كان المستخدم صاحب صالون
        if (in_array('salon_owner', $roles)) {
            $salon = $user->ownedSalon;
            if ($salon) {
                $data['salon'] = [
                    'id' => $salon->id,
                    'name' => $salon->name,
                    'address' => $salon->address,
                    'phone' => $salon->phone,
                    'image' => $salon->getMainImageUrlAttribute(),
                ];
            }
        }

        // إذا كان المستخدم حلاق
        if (in_array('barber', $roles)) {
            $data['salons'] = $user->salons->map(function($salon) {
                return [
                    'id' => $salon->id,
                    'name' => $salon->name,
                    'address' => $salon->address,
                ];
            });
        }

        return $data;
    }

    /**
     * إنشاء مستخدم جديد (مع is_active = false)
     */
    private function createUser(RegisterRequest $request, string $userType): User
    {
        $name = $userType === 'salon_owner' ? $request->owner_name : $request->name;

        return User::create([
            'name' => $name,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'role' => $userType,
            'is_active' => false, // غير مفعل حتى يتم التحقق
        ]);
    }

    /**
     * إنشاء صالون لصاحب الصالون
     */
    private function createSalonForOwner(RegisterRequest $request, User $user): void
    {
        Salon::create([
            'name' => $request->salon_name,
            'owner_id' => $user->id,
            'address' => $request->salon_location,
            'phone' => $request->salon_phone ?? $request->phone,
            'description' => $request->salon_description ?? null,
            'latitude' => $request->latitude ?? null,
            'longitude' => $request->longitude ?? null,
            'is_active' => true,
        ]);
    }

    /**
     * تعيين الدور للمستخدم
     */
    private function assignRole(User $user, string $userType): void
    {
        $user->assignRole($userType);
    }

    /**
     * تحديد نوع المستخدم
     */
    private function determineUserType(RegisterRequest $request): string
    {
        return $request->has('salon_name') ? 'salon_owner' : 'customer';
    }

    /**
     * إنشاء توكن
     */
    private function generateToken(User $user): string
    {
        return $user->createToken('auth_token')->plainTextToken;
    }

    /**
     * تحميل العلاقات حسب نوع المستخدم
     */
    private function loadUserRelations(User $user): void
    {
        $roles = $user->getRoleNames()->toArray();

        if (in_array('salon_owner', $roles)) {
            $user->load('ownedSalon');
        }

        if (in_array('barber', $roles)) {
            $user->load('salons');
        }
    }

    /**
     * رسالة نجاح حسب الدور
     */
    private function getSuccessMessage(User $user): string
    {
        $roles = $user->getRoleNames()->toArray();

        if (in_array('salon_owner', $roles)) {
            return 'تم تسجيل الصالون بنجاح';
        }

        return 'تم تسجيل العميل بنجاح';
    }

/**
 *  طلب إعادة تعيين كلمة المرور (إرسال OTP)
 */
public function forgotPassword(string $phone): AuthResult
{
    try {
        $user = User::where('phone', $phone)->first();

        if (!$user) {
            return AuthResult::error('لا يوجد حساب مرتبط بهذا الرقم', null, 404);
        }

        // توليد OTP
        $otpCode = $this->otpService->generateOTP();

        // تخزين OTP مع نوع العملية (reset_password)
        $cacheKey = "password_reset_{$user->id}";
        Cache::put($cacheKey, [
            'otp' => $otpCode,
            'attempts' => 0,
            'type' => 'reset_password',
            'created_at' => now(),
        ], now()->addMinutes(10));

        // إرسال OTP عبر واتساب
        $message = " *إعادة تعيين كلمة المرور*\n\n" .
                   "مرحباً {$user->name}،\n\n" .
                   "لقد طلبت إعادة تعيين كلمة المرور.\n\n" .
                   "رمز التحقق الخاص بك هو:\n\n" .
                   "*{$otpCode}*\n\n" .
                   " هذا الرمز صالح لمدة 10 دقائق فقط.\n" .
                   "إذا لم تطلب هذا، يمكنك تجاهل هذه الرسالة.";

        $result = $this->whatsappService->sendMessage($phone, $message, 1);

        if (!$result['success']) {
            return AuthResult::error('فشل إرسال رمز التحقق، يرجى المحاولة لاحقاً', null, 500);
        }

        Log::info('Password reset OTP sent', [
            'user_id' => $user->id,
            'phone' => $phone,
        ]);

        return AuthResult::success('تم إرسال رمز التحقق إلى رقم واتساب الخاص بك', [
            'user_id' => $user->id,
            'phone' => $user->phone,
            'expires_in' => 600,
        ], 200);

    } catch (\Exception $e) {
        Log::error('Forgot password error: ' . $e->getMessage());
        return AuthResult::error('حدث خطأ أثناء إرسال رمز التحقق', null, 500);
    }
}

/**
 * التحقق من OTP وإعادة تعيين كلمة المرور
 */
public function resetPassword(string $phone, string $otpCode, string $newPassword): AuthResult
{
    try {
        $user = User::where('phone', $phone)->first();

        if (!$user) {
            return AuthResult::error('المستخدم غير موجود', null, 404);
        }

        $cacheKey = "password_reset_{$user->id}";
        $resetData = Cache::get($cacheKey);

        if (!$resetData) {
            return AuthResult::error('رمز التحقق منتهي الصلاحية أو غير موجود، يرجى طلب رمز جديد', null, 400);
        }

        // التحقق من عدد المحاولات
        if ($resetData['attempts'] >= 3) {
            Cache::forget($cacheKey);
            return AuthResult::error('تم تجاوز عدد المحاولات المسموح به، يرجى طلب رمز جديد', null, 400);
        }

        // التحقق من صحة الرمز
        if ($resetData['otp'] !== $otpCode) {
            $resetData['attempts']++;
            Cache::put($cacheKey, $resetData, now()->diffInSeconds($resetData['created_at']->addMinutes(10)));

            $remainingAttempts = 3 - $resetData['attempts'];
            return AuthResult::error("رمز التحقق غير صحيح. محاولات متبقية: {$remainingAttempts}", null, 400);
        }

        // تحديث كلمة المرور
        $user->update([
            'password' => Hash::make($newPassword),
        ]);

        // حذف جميع التوكنات القديمة (تسجيل الخروج من جميع الأجهزة)
        $user->tokens()->delete();

        // تنظيف الـ Cache
        Cache::forget($cacheKey);

        // إرسال رسالة تأكيد عبر واتساب
        $this->sendPasswordChangedConfirmation($user);

        Log::info('Password reset successfully', [
            'user_id' => $user->id,
            'phone' => $phone,
        ]);

        // إنشاء توكن جديد للمستخدم (اختياري)
        $token = $this->generateToken($user);
        $userData = $this->formatUserData($user, $token);

        return AuthResult::success('تم إعادة تعيين كلمة المرور بنجاح', $userData, 200);

    } catch (\Exception $e) {
        Log::error('Reset password error: ' . $e->getMessage());
        return AuthResult::error('حدث خطأ أثناء إعادة تعيين كلمة المرور', null, 500);
    }
}

/**
 *  إرسال رسالة تأكيد تغيير كلمة المرور
 */
private function sendPasswordChangedConfirmation(User $user): void
{
    $message = " *تم تغيير كلمة المرور بنجاح* \n\n" .
               "مرحباً {$user->name}،\n\n" .
               "تم تغيير كلمة المرور الخاصة بحسابك بنجاح.\n\n" .
               "إذا لم تقم بذلك، يرجى التواصل مع الدعم فوراً.\n\n" .
               "يمكنك الآن تسجيل الدخول باستخدام كلمة المرور الجديدة.";

    $this->whatsappService->sendMessage($user->phone, $message, 1);
}

/**
 *  التحقق من صلاحية رمز إعادة التعيين (اختياري)
 */
public function checkResetOTPStatus(int $userId): AuthResult
{
    try {
        $user = User::find($userId);

        if (!$user) {
            return AuthResult::error('المستخدم غير موجود', null, 404);
        }

        $cacheKey = "password_reset_{$userId}";
        $resetData = Cache::get($cacheKey);

        if (!$resetData) {
            return AuthResult::error('لا يوجد رمز فعال لإعادة التعيين', null, 404);
        }

        $remainingAttempts = 3 - $resetData['attempts'];
        $remainingSeconds = now()->diffInSeconds($resetData['created_at']->addMinutes(10));

        return AuthResult::success('رمز إعادة التعيين فعال', [
            'has_active_code' => true,
            'remaining_attempts' => $remainingAttempts,
            'remaining_seconds' => $remainingSeconds,
            'expires_at' => $resetData['created_at']->addMinutes(10)->toISOString(),
        ]);

    } catch (\Exception $e) {
        Log::error('Check reset OTP status error: ' . $e->getMessage());
        return AuthResult::error('حدث خطأ', null, 500);
    }
}
}
