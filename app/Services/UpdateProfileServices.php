<?php

namespace App\Services;

use App\Models\User;
use App\Http\Resources\UserResource;
use App\Services\AuthResult;
use App\Http\Requests\UpdateProfileRequest;
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
     * تحديث الملف الشخصي (شامل الصورة وكلمة المرور والإعدادات)
     */
    public function updateProfile(UpdateProfileRequest $request): AuthResult
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return AuthResult::error('المستخدم غير موجود', null, 404);
            }

            $passwordChanged = false;
            $token = null;

            // ========== 1. التحقق من كلمة المرور الحالية إذا تم تغيير كلمة المرور ==========
            if ($request->filled('password')) {
                if (!Hash::check($request->current_password, $user->password)) {
                    return AuthResult::error('كلمة المرور الحالية غير صحيحة', null, 400);
                }
                $user->password = Hash::make($request->password);
                $passwordChanged = true;
            }

            // ========== 2. تحديث البيانات الأساسية ==========
            $updateData = [];

            if ($request->filled('name')) {
                $updateData['name'] = $request->name;
            }

            if ($request->filled('phone')) {
                // التحقق من عدم تكرار رقم الهاتف
                $existingUser = User::where('phone', $request->phone)
                    ->where('id', '!=', $user->id)
                    ->first();

                if ($existingUser) {
                    return AuthResult::error('رقم الهاتف مستخدم من قبل', null, 422);
                }
                $updateData['phone'] = $request->phone;
            }

            if ($request->filled('email')) {
                $updateData['email'] = $request->email;
            }

            if (!empty($updateData)) {
                $user->update($updateData);
            }

            // ========== 3. معالجة الصورة الشخصية ==========
            // 3.1 إذا طلب حذف الصورة
            if ($request->boolean('delete_avatar')) {
                $user->clearMediaCollection('avatar');
                Log::info('Avatar deleted on update', ['user_id' => $user->id]);
            }

            // 3.2 إذا تم رفع صورة جديدة
            if ($request->hasFile('avatar')) {
                // حذف الصورة القديمة
                $user->clearMediaCollection('avatar');
                // إضافة الصورة الجديدة
                $user->addMedia($request->file('avatar'))
                    ->usingFileName($this->generateFileName($request->file('avatar')))
                    ->toMediaCollection('avatar');
                Log::info('Avatar updated on profile update', ['user_id' => $user->id]);
            }

            // ========== 4. تحديث إعدادات الإشعارات ==========
            if ($request->has('notifications_enabled')) {
                $user->notifications_enabled = $request->boolean('notifications_enabled');
                $user->save();
                Log::info('Notification settings updated', [
                    'user_id' => $user->id,
                    'enabled' => $request->boolean('notifications_enabled')
                ]);
            }

            // ========== 5. تحديث بيانات الصالون (لصاحب الصالون) ==========
            if ($user->hasRole('salon_owner') && $user->ownedSalon) {
                $this->updateSalonData($user, $request);
            }

            // ========== 6. تحديث بيانات الحلاق ==========
            if ($user->hasRole('barber')) {
                $this->updateBarberData($user, $request);
            }

            // ========== 7. إنشاء توكن جديد إذا تم تغيير كلمة المرور ==========
            if ($passwordChanged) {
                // حذف جميع التوكنات القديمة
                $user->tokens()->delete();
                // إنشاء توكن جديد
                $token = $this->generateToken($user);
            }

            Log::info('Profile updated successfully', [
                'user_id' => $user->id,
                'password_changed' => $passwordChanged
            ]);

            
            $userData = UserResource::make($user->fresh());

            // إضافة التوكن الجديد إذا تم تغيير كلمة المرور
            if ($passwordChanged && $token) {
                $responseData = $userData->toArray(request());
                $responseData['token'] = $token;
                $responseData['token_type'] = 'Bearer';

                return AuthResult::success('تم تحديث الملف الشخصي بنجاح، تم تغيير كلمة المرور', $responseData);
            }

            return AuthResult::success('تم تحديث الملف الشخصي بنجاح', $userData);

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
     * تحديث الصورة الشخصية فقط (منفصل - للتوافق مع الإصدارات القديمة)
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

            Log::info('Avatar updated via separate endpoint', ['user_id' => $user->id]);

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
     * تغيير كلمة المرور فقط (منفصل)
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
     * تحديث إعدادات الإشعارات فقط (منفصل)
     */
    public function updateNotifications(bool $enabled): AuthResult
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return AuthResult::error('المستخدم غير موجود', null, 404);
            }

            $user->notifications_enabled = $enabled;
            $user->save();

            Log::info('Notification settings updated', [
                'user_id' => $user->id,
                'enabled' => $enabled
            ]);

            return AuthResult::success(
                $enabled ? 'تم تفعيل الإشعارات' : 'تم إيقاف الإشعارات',
                ['notifications_enabled' => $enabled]
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
     * حذف الحساب بالكامل
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

            // حذف بيانات الصالون إذا كان صاحب صالون
            if ($user->hasRole('salon_owner') && $user->ownedSalon) {
                $user->ownedSalon->delete();
            }

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
    private function updateSalonData(User $user, UpdateProfileRequest $request): void
    {
        $salonData = [];

        if ($request->filled('salon_name')) {
            $salonData['name'] = $request->salon_name;
        }

        if ($request->filled('salon_address')) {
            $salonData['address'] = $request->salon_address;
        }

        if ($request->filled('salon_phone')) {
            $salonData['phone'] = $request->salon_phone;
        }

        if ($request->filled('salon_description')) {
            $salonData['description'] = $request->salon_description;
        }

        if (!empty($salonData)) {
            $user->ownedSalon->update($salonData);
            Log::info('Salon data updated', ['user_id' => $user->id, 'salon_id' => $user->ownedSalon->id]);
        }
    }

    /**
     * تحديث بيانات الحلاق
     */
    private function updateBarberData(User $user, UpdateProfileRequest $request): void
    {
        $barberData = [];

        if ($request->filled('experience_years')) {
            $barberData['experience_years'] = $request->experience_years;
        }

        if ($request->filled('specialization')) {
            $barberData['specialization'] = $request->specialization;
        }

        if (!empty($barberData)) {
            // إذا كان لديك جدول منفصل لبيانات الحلاق
            if ($user->barberProfile) {
                $user->barberProfile->update($barberData);
                Log::info('Barber data updated', ['user_id' => $user->id]);
            }
        }
    }
}
