<?php
// app/Services/Salon/RegisterService.php

namespace App\Services\Salon;

use App\Models\User;
use App\Models\Salon;
use App\Models\WorkingHour;
use App\Services\AuthResult;
use App\Services\Notification\FirebaseNotificationService;
use App\Services\ultraMessage\UltraMsgService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\UploadedFile;
use App\Services\Notification\SalonNotificationService;

class RegisterService
{
    protected UltraMsgService $ultraMsg;
    protected const OTP_EXPIRY_MINUTES = 10;
    protected const OTP_PREFIX = 'salon_registration_otp_';
    protected const DATA_PREFIX = 'salon_registration_data_';

    public function __construct(UltraMsgService $ultraMsg)
    {
        $this->ultraMsg = $ultraMsg;
    }

    /**
     * الخطوة 1: إرسال كود التحقق لرقم الهاتف
     */
    public function sendVerificationCode(array $data): AuthResult
    {
        try {
            $phone = $data['phone'];

            // التحقق من أن الرقم غير مستخدم مسبقاً
            $existingUser = User::where('phone', $phone)->first();
            if ($existingUser) {
                return AuthResult::error('رقم الهاتف مستخدم بالفعل', null, 422);
            }

            // إنشاء كود تحقق عشوائي (6 أرقام)
            $otpCode = sprintf("%06d", rand(0, 999999));

            // تخزين البيانات المؤقتة في Cache
            $this->storeTemporaryData($phone, $data);

            // تخزين كود التحقق
            $this->storeOtpCode($phone, $otpCode);

            // إرسال كود التحقق عبر WhatsApp
            $this->sendOtpMessage($phone, $otpCode);

            Log::info('Verification code sent', ['phone' => $phone]);

            return AuthResult::success(
                'تم إرسال رمز التحقق إلى رقم هاتفك',
                [
                    'phone' => $phone,
                    'expires_in' => self::OTP_EXPIRY_MINUTES,
                    'resend_after' => 60 // ثانية
                ],
                200
            );

        } catch (\Exception $e) {
            Log::error('Send verification code error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء إرسال رمز التحقق: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * الخطوة 2: إعادة إرسال كود التحقق
     */
    public function resendVerificationCode(string $phone): AuthResult
    {
        try {
            // التحقق من وجود بيانات مؤقتة
            $tempData = $this->getTemporaryData($phone);
            if (!$tempData) {
                return AuthResult::error('لا توجد عملية تسجيل نشطة لهذا الرقم', null, 404);
            }

            // إنشاء كود تحقق جديد
            $otpCode = sprintf("%06d", rand(0, 999999));

            // تحديث كود التحقق
            $this->storeOtpCode($phone, $otpCode);

            // إرسال كود التحقق عبر WhatsApp
            $this->sendOtpMessage($phone, $otpCode);

            Log::info('Verification code resent', ['phone' => $phone]);

            return AuthResult::success(
                'تم إعادة إرسال رمز التحقق إلى رقم هاتفك',
                [
                    'phone' => $phone,
                    'expires_in' => self::OTP_EXPIRY_MINUTES,
                ],
                200
            );

        } catch (\Exception $e) {
            Log::error('Resend verification code error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء إعادة إرسال رمز التحقق', null, 500);
        }
    }

    /**
     * الخطوة 3: التحقق من الكود وإنشاء الحساب (غير مفعل بعد)
     */
    public function verifyCodeAndCreate(string $phone, string $code): AuthResult
    {
        try {
            // التحقق من صحة الكود
            $storedCode = $this->getOtpCode($phone);
            if (!$storedCode || $storedCode !== $code) {
                return AuthResult::error('رمز التحقق غير صحيح أو منتهي الصلاحية', null, 422);
            }

            // استرجاع البيانات المؤقتة
            $data = $this->getTemporaryData($phone);
            if (!$data) {
                return AuthResult::error('انتهت صلاحية الجلسة، يرجى إعادة المحاولة', null, 422);
            }

            // إنشاء الحساب (غير مفعل)
            $result = $this->createSalonOwnerAccount($data);

            if ($result->isSuccess()) {
                // حذف البيانات المؤقتة
                $this->clearTemporaryData($phone);
                $this->clearOtpCode($phone);

                Log::info('Salon owner account created (pending activation)', [
                    'user_id' => $result->getData()['user']['id'] ?? null,
                    'phone' => $phone
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Verify code error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء التحقق: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * إنشاء حساب صاحب الصالون (غير مفعل - ينتظر موافقة المدير)
     */
    private function createSalonOwnerAccount(array $data): AuthResult
    {
        try {
            return DB::transaction(function () use ($data) {

                // 1. إنشاء المستخدم (غير مفعل)
                $user = User::create([
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                    'password' => Hash::make($data['password']),
                    'role' => 'salon_owner',
                    'is_active' => false, //  غير مفعل حتى يوافق عليه المدير
                ]);

                // 2. رفع الصورة الشخصية إذا وجدت
                if (isset($data['avatar']) && $data['avatar'] instanceof UploadedFile) {
                    $this->uploadAvatar($user, $data['avatar']);
                }

                // 3. تعيين الأدوار (معلقة حتى التفعيل)
                $this->assignRoles($user, $data);

                // 4. إنشاء الصالون (غير مفعل)
                $salon = Salon::create([
                    'name' => $data['salon_name'],
                    'owner_id' => $user->id,
                    'address' => $data['salon_address'],
                    'phone' => $data['salon_phone'] ?? $data['phone'],
                    'latitude' => $data['latitude'] ?? null,
                    'longitude' => $data['longitude'] ?? null,
                    'is_active' => false, // غير مفعل حتى يوافق عليه المدير
                ]);

                // 5. رفع صور الصالون
                if (isset($data['images']) && is_array($data['images'])) {
                    $this->uploadMultipleImages($salon, $data['images']);
                }

                // 6. إضافة أوقات العمل للصالون
                if (isset($data['working_hours']) && !empty($data['working_hours'])) {
                    $this->saveWorkingHours($salon, $data['working_hours']);
                }

                // 7. إذا كان يعمل كحلاق، أضفه للصالون
                if (!empty($data['works_as_barber'])) {
                    $this->addBarberToSalon($user, $salon, $data);
                }

                // 8. إرسال إشعار للمديرين لتفعيل الحساب
                $this->notifyAdminsForApproval($salon, $user);

                $result = [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'phone' => $user->phone,
                        'role' => $user->role,
                        'roles' => $user->getRoleNames(),
                        'works_as_barber' => !empty($data['works_as_barber']),
                        'avatar' => $user->getAvatarUrlAttribute(),
                        'is_active' => false, // ينتظر التفعيل
                        'status' => 'pending_approval'
                    ],
                    'salon' => [
                        'id' => $salon->id,
                        'name' => $salon->name,
                        'address' => $salon->address,
                        'phone' => $salon->phone,
                        'latitude' => $salon->latitude,
                        'longitude' => $salon->longitude,
                        'images' => $salon->getImagesUrlsAttribute(),
                        'working_hours' => $this->getWorkingHoursFormatted($salon),
                        'is_active' => false, // ينتظر التفعيل
                    ],
                    'message' => 'تم إنشاء الحساب بنجاح، ينتظر موافقة المدير'
                ];

                return AuthResult::success('تم إنشاء الحساب بنجاح، ينتظر موافقة المدير', $result, 201);

            });
        } catch (\Exception $e) {
            Log::error('Create salon owner account error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء إنشاء الحساب: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * تفعيل حساب صاحب الصالون بواسطة المدير
     */
    public function approveSalonOwner(int $userId, int $adminId): AuthResult
    {
        try {
            return DB::transaction(function () use ($userId, $adminId) {

                $user = User::find($userId);
                if (!$user) {
                    return AuthResult::error('المستخدم غير موجود', null, 404);
                }

                if ($user->is_active) {
                    return AuthResult::error('الحساب مفعل بالفعل', null, 400);
                }

                $salon = $user->ownedSalon;
                if (!$salon) {
                    return AuthResult::error('لا يوجد صالون مرتبط بهذا المستخدم', null, 404);
                }

                // تفعيل المستخدم
                $user->is_active = true;
                $user->save();

                // تفعيل الصالون
                $salon->is_active = true;
                $salon->save();

                // إذا كان يعمل كحلاق، قم بتفعيل علاقته بالصالون
                if ($user->hasRole('barber')) {
                    $user->salons()->updateExistingPivot($salon->id, [
                        'is_active' => true
                    ]);
                }

                // إرسال إشعار لصاحب الصالون بأن حسابه تم تفعيله
                $this->sendActivationNotification($user, $salon);

                Log::info('Salon owner account approved', [
                    'user_id' => $userId,
                    'salon_id' => $salon->id,
                    'approved_by' => $adminId
                ]);

                return AuthResult::success('تم تفعيل حساب صاحب الصالون بنجاح', [
                    'user_id' => $user->id,
                    'salon_id' => $salon->id,
                    'is_active' => true
                ], 200);

            });
        } catch (\Exception $e) {
            Log::error('Approve salon owner error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء تفعيل الحساب: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * رفض طلب تسجيل صاحب صالون
     */
    public function rejectSalonOwner(int $userId, int $adminId, ?string $reason = null): AuthResult
    {
        try {
            $user = User::find($userId);
            if (!$user) {
                return AuthResult::error('المستخدم غير موجود', null, 404);
            }

            $salon = $user->ownedSalon;

            // حذف المستخدم والبيانات المرتبطة
            if ($salon) {
                $salon->delete();
            }
            $user->delete();

            // إرسال إشعار الرفض للمستخدم
            $this->sendRejectionNotification($user->phone, $reason);

            Log::info('Salon owner registration rejected', [
                'user_id' => $userId,
                'rejected_by' => $adminId,
                'reason' => $reason
            ]);

            return AuthResult::success('تم رفض طلب التسجيل', null, 200);

        } catch (\Exception $e) {
            Log::error('Reject salon owner error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء رفض الطلب: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * جلب طلبات التسجيل المعلقة (للمديرين)
     */
    public function getPendingRegistrations(): AuthResult
    {
        try {
            $pendingUsers = User::where('role', 'salon_owner')
                ->where('is_active', false)
                ->with('ownedSalon')
                ->get();

            $result = $pendingUsers->map(function ($user) {
                return [
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'created_at' => $user->created_at,
                    'salon' => $user->ownedSalon ? [
                        'id' => $user->ownedSalon->id,
                        'name' => $user->ownedSalon->name,
                        'address' => $user->ownedSalon->address,
                        'phone' => $user->ownedSalon->phone,
                    ] : null,
                ];
            });

            return AuthResult::success('تم جلب الطلبات المعلقة', $result, 200);

        } catch (\Exception $e) {
            Log::error('Get pending registrations error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب الطلبات', null, 500);
        }
    }

    // ===================== دوال مساعدة لتخزين البيانات المؤقتة =====================

    /**
     * تخزين البيانات المؤقتة في Cache
     */
    private function storeTemporaryData(string $phone, array $data): void
    {
        $key = self::DATA_PREFIX . $phone;
        Cache::put($key, $data, now()->addMinutes(self::OTP_EXPIRY_MINUTES));
    }

    /**
     * استرجاع البيانات المؤقتة
     */
    private function getTemporaryData(string $phone): ?array
    {
        $key = self::DATA_PREFIX . $phone;
        return Cache::get($key);
    }

    /**
     * حذف البيانات المؤقتة
     */
    private function clearTemporaryData(string $phone): void
    {
        $key = self::DATA_PREFIX . $phone;
        Cache::forget($key);
    }

    /**
     * تخزين كود التحقق
     */
    private function storeOtpCode(string $phone, string $code): void
    {
        $key = self::OTP_PREFIX . $phone;
        Cache::put($key, $code, now()->addMinutes(self::OTP_EXPIRY_MINUTES));
    }

    /**
     * استرجاع كود التحقق
     */
    private function getOtpCode(string $phone): ?string
    {
        $key = self::OTP_PREFIX . $phone;
        return Cache::get($key);
    }

    /**
     * حذف كود التحقق
     */
    private function clearOtpCode(string $phone): void
    {
        $key = self::OTP_PREFIX . $phone;
        Cache::forget($key);
    }

    /**
     * إرسال كود التحقق عبر WhatsApp
     */
    private function sendOtpMessage(string $phone, string $otpCode): void
    {
        try {
            $message = "رمز التحقق الخاص بك هو: {$otpCode}\n"
                     . "هذا الرمز صالح لمدة " . self::OTP_EXPIRY_MINUTES . " دقائق.\n"
                     . "لا تشارك هذا الرمز مع أي شخص.";

            $this->ultraMsg->sendMessage($phone, $message, 5);
        } catch (\Exception $e) {
            Log::error('Failed to send OTP message: ' . $e->getMessage());
        }
    }

    /**
     * إرسال إشعار للمديرين لتفعيل الحساب
     */
    private function notifyAdminsForApproval(Salon $salon, User $user): void
    {
        try {
            $notificationService = app(FirebaseNotificationService::class);
            $notificationService->notifyAdminsAboutNewSalon($salon, $user);
        } catch (\Exception $e) {
            Log::error('Failed to send admin notification: ' . $e->getMessage());
        }
    }

    /**
     * إرسال إشعار تفعيل الحساب لصاحب الصالون
     */
    private function sendActivationNotification(User $user, Salon $salon): void
    {
        try {
            $message = "تهانينا! تم تفعيل حساب صالون {$salon->name} بنجاح.\n"
                     . "يمكنك الآن تسجيل الدخول والبدء في إدارة صالونك.\n"
                     . "رابط التطبيق: " . env('APP_URL');

            $this->ultraMsg->sendMessage($user->phone, $message, 5);
        } catch (\Exception $e) {
            Log::error('Failed to send activation notification: ' . $e->getMessage());
        }
    }

    /**
     * إرسال إشعار رفض التسجيل
     */
    private function sendRejectionNotification(string $phone, ?string $reason = null): void
    {
        try {
            $message = "نأسف لإبلاغك أنه تم رفض طلب تسجيل صالونك.\n";
            if ($reason) {
                $message .= "السبب: {$reason}\n";
            }
            $message .= "يمكنك التواصل مع الدعم الفني للمزيد من المعلومات.";

            $this->ultraMsg->sendMessage($phone, $message, 5);
        } catch (\Exception $e) {
            Log::error('Failed to send rejection notification: ' . $e->getMessage());
        }
    }

    // ===================== باقي الدوال المساعدة (بدون تغيير) =====================

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

    private function generateAvatarFileName(UploadedFile $file): string
    {
        return 'avatar_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
    }

    private function assignRoles(User $user, array $data): void
    {
        $user->assignRole('salon_owner');
        if (!empty($data['works_as_barber'])) {
            $user->assignRole('barber');
            Log::info('User assigned as barber too', ['user_id' => $user->id]);
        }
    }

    private function addBarberToSalon(User $user, Salon $salon, array $data): void
    {
        $user->salons()->attach($salon->id, [
            'is_active' => false, // غير مفعل حتى موافقة المدير
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function uploadMultipleImages(Salon $salon, array $images): void
    {
        foreach ($images as $image) {
            if ($image instanceof UploadedFile) {
                try {
                    $salon->addMedia($image)
                        ->usingFileName('salon_' . time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension())
                        ->toMediaCollection('salon_images');
                } catch (\Exception $e) {
                    Log::error('Image upload failed: ' . $e->getMessage());
                }
            }
        }
    }

    private function saveWorkingHours(Salon $salon, array $workingHours): void
    {
        foreach ($workingHours as $hours) {
            WorkingHour::create([
                'workable_type' => Salon::class,
                'workable_id' => $salon->id,
                'day_of_week' => $hours['day'],
                'is_open' => $hours['is_open'],
                'shift1_start' => $hours['start'] ?? $hours['shift1_start'] ?? null,
                'shift1_end' => $hours['end'] ?? $hours['shift1_end'] ?? null,
            ]);
        }
    }

    private function getWorkingHoursFormatted(Salon $salon): array
    {
        $daysInArabic = [
            'sunday' => 'الأحد',
            'monday' => 'الإثنين',
            'tuesday' => 'الثلاثاء',
            'wednesday' => 'الأربعاء',
            'thursday' => 'الخميس',
            'friday' => 'الجمعة',
            'saturday' => 'السبت',
        ];

        $workingHours = $salon->workingHours()
            ->orderByRaw("FIELD(day_of_week, 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday')")
            ->get();

        $result = [];
        foreach ($workingHours as $hour) {
            $result[] = [
                'day' => $hour->day_of_week,
                'day_ar' => $daysInArabic[$hour->day_of_week],
                'is_open' => (bool) $hour->is_open,
                'start' => $hour->shift1_start,
                'end' => $hour->shift1_end,
                'time_range' => ($hour->is_open && $hour->shift1_start && $hour->shift1_end)
                    ? $hour->shift1_start . ' - ' . $hour->shift1_end
                    : null,
            ];
        }
        return $result;
    }
}
