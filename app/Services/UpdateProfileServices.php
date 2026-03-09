<?php
// app/Services/Profile/ProfileService.php

namespace App\Services;

use App\Models\User;
use App\Http\Resources\UserResource;
use App\Services\AuthResult;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UpdateProfileServices
{
    /**
     * الحصول على الملف الشخصي
     */
    public function getProfile(): AuthResult
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return AuthResult::error('المستخدم غير موجود', null, 404);
            }

            // تحميل العلاقات حسب الدور
            $this->loadUserRelations($user);

            return AuthResult::success(
                'الملف الشخصي',
                UserResource::make($user)
            );

        } catch (\Exception $e) {
            Log::error('Get profile error: ' . $e->getMessage());

            return AuthResult::error(
                'حدث خطأ أثناء جلب الملف الشخصي',
                config('app.debug') ? $e->getMessage() : null,
                500
            );
        }
    }

    /**
     * تحديث الملف الشخصي
     */
    public function updateProfile(array $data, bool $passwordChanged = false): AuthResult
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return AuthResult::error('المستخدم غير موجود', null, 404);
            }

            if (empty($data)) {
                return AuthResult::error('لا توجد بيانات للتحديث', null, 400);
            }

            // تحديث بيانات المستخدم
            $user->update($data);

            // تحديث بيانات الصالون إذا وجدت
            if ($user->hasRole('salon_owner') && $user->ownedSalon) {
                $this->updateSalonData($user, $data);
            }

            // تحديث بيانات الحلاق إذا وجدت
            if ($user->hasRole('barber')) {
                $this->updateBarberData($user, $data);
            }

            // إنشاء توكن جديد إذا تم تغيير كلمة المرور
            if ($passwordChanged) {
                $user->tokens()->delete();
                $token = $this->generateToken($user);
                $user->token = $token;
            }

            Log::info('Profile updated', ['user_id' => $user->id]);

            return AuthResult::success(
                'تم تحديث الملف الشخصي بنجاح',
                UserResource::make($user->fresh())
            );

        } catch (\Exception $e) {
            Log::error('Update profile error: ' . $e->getMessage());

            return AuthResult::error(
                'حدث خطأ أثناء تحديث الملف الشخصي',
                config('app.debug') ? $e->getMessage() : null,
                500
            );
        }
    }

    /**
     * تحديث الصورة الشخصية
     */
    public function updateAvatar(UploadedFile $avatar): AuthResult
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return AuthResult::error('المستخدم غير موجود', null, 404);
            }

            // حذف الصورة القديمة
            $user->clearMediaCollection('avatar');

            // إضافة الصورة الجديدة
            $user->addMedia($avatar)
                ->usingFileName($this->generateFileName($avatar))
                ->toMediaCollection('avatar');

            Log::info('Avatar updated', ['user_id' => $user->id]);

            return AuthResult::success(
                'تم تحديث الصورة الشخصية بنجاح',
                [
                    'avatar' => $user->avatar_url,
                    'avatar_thumb' => $user->avatar_thumb_url,
                ]
            );

        } catch (\Exception $e) {
            Log::error('Update avatar error: ' . $e->getMessage());

            return AuthResult::error(
                'حدث خطأ أثناء تحديث الصورة',
                config('app.debug') ? $e->getMessage() : null,
                500
            );
        }
    }

    /**
     * حذف الصورة الشخصية
     */
    public function deleteAvatar(): AuthResult
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return AuthResult::error('المستخدم غير موجود', null, 404);
            }

            if (!$user->hasAvatar()) {
                return AuthResult::error('لا توجد صورة للحذف', null, 400);
            }

            $user->clearMediaCollection('avatar');

            Log::info('Avatar deleted', ['user_id' => $user->id]);

            return AuthResult::success(
                'تم حذف الصورة الشخصية بنجاح',
                ['avatar' => null]
            );

        } catch (\Exception $e) {
            Log::error('Delete avatar error: ' . $e->getMessage());

            return AuthResult::error(
                'حدث خطأ أثناء حذف الصورة',
                config('app.debug') ? $e->getMessage() : null,
                500
            );
        }
    }

    /**
     * تغيير كلمة المرور
     */
    public function changePassword(string $currentPassword, string $newPassword): AuthResult
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return AuthResult::error('المستخدم غير موجود', null, 404);
            }

            if (!Hash::check($currentPassword, $user->password)) {
                return AuthResult::error('كلمة المرور الحالية غير صحيحة', null, 400);
            }

            $user->password = Hash::make($newPassword);
            $user->save();

            // تسجيل الخروج من جميع الأجهزة (اختياري)
            // $user->tokens()->delete();

            Log::info('Password changed', ['user_id' => $user->id]);

            return AuthResult::success('تم تغيير كلمة المرور بنجاح');

        } catch (\Exception $e) {
            Log::error('Change password error: ' . $e->getMessage());

            return AuthResult::error(
                'حدث خطأ أثناء تغيير كلمة المرور',
                config('app.debug') ? $e->getMessage() : null,
                500
            );
        }
    }

    /**
     * تحديث حالة الإشعارات
     */
    public function updateNotifications(bool $enabled): AuthResult
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return AuthResult::error('المستخدم غير موجود', null, 404);
            }

            // تحديث حالة الإشعارات (إذا كان لديك حقل في جدول users)
            // $user->notifications_enabled = $enabled;
            // $user->save();

            Log::info('Notifications updated', [
                'user_id' => $user->id,
                'enabled' => $enabled
            ]);

            return AuthResult::success(
                $enabled ? 'تم تفعيل الإشعارات' : 'تم إيقاف الإشعارات'
            );

        } catch (\Exception $e) {
            Log::error('Update notifications error: ' . $e->getMessage());

            return AuthResult::error(
                'حدث خطأ أثناء تحديث الإشعارات',
                config('app.debug') ? $e->getMessage() : null,
                500
            );
        }
    }

    /**
     * حذف الحساب
     */
    public function deleteAccount(): AuthResult
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return AuthResult::error('المستخدم غير موجود', null, 404);
            }

            // حذف التوكنات
            $user->tokens()->delete();

            // حذف الصور
            $user->clearMediaCollection('avatar');

            // حذف المستخدم
            $user->delete();

            Log::info('Account deleted', ['user_id' => $user->id]);

            return AuthResult::success('تم حذف الحساب بنجاح');

        } catch (\Exception $e) {
            Log::error('Delete account error: ' . $e->getMessage());

            return AuthResult::error(
                'حدث خطأ أثناء حذف الحساب',
                config('app.debug') ? $e->getMessage() : null,
                500
            );
        }
    }

    /**
     * إنشاء توكن جديد
     */
    private function generateToken(User $user): string
    {
        return $user->createToken('auth_token')->plainTextToken;
    }

    /**
     * توليد اسم فريد للملف
     */
    private function generateFileName(UploadedFile $file): string
    {
        return 'avatar_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
    }

    /**
     * تحميل العلاقات حسب الدور
     */
    private function loadUserRelations(User $user): void
    {
        if ($user->hasRole('salon_owner')) {
            $user->load('ownedSalon');
        } elseif ($user->hasRole('barber')) {
            $user->load('salons');
        }
    }

    /**
     * تحديث بيانات الصالون
     */
    private function updateSalonData(User $user, array $data): void
    {
        $salonFields = ['center_name', 'address', 'salon_phone', 'description'];
        $salonData = array_intersect_key($data, array_flip($salonFields));

        if (!empty($salonData)) {
            $user->ownedSalon->update($salonData);
        }
    }

    /**
     * تحديث بيانات الحلاق
     */
    private function updateBarberData(User $user, array $data): void
    {
        $barberFields = ['experience_years', 'specialization'];
        $barberData = array_intersect_key($data, array_flip($barberFields));

        if (!empty($barberData)) {
            // إذا كان لديك جدول منفصل لبيانات الحلاق
            // $user->barberProfile->update($barberData);
        }
    }
}
