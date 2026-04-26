<?php
// app/Services/Auth/AuthAllUserServices.php

namespace App\Services;

use App\Models\User;
use App\Models\Salon;
use App\Http\Requests\RegisterRequest;
use App\Services\AuthResult;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;

class AuthAllUserServices
{
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
                    'الحساب غير مفعل. يرجى التواصل مع الدعم',
                    null,
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
     * تسجيل مستخدم جديد (مع دعم الصورة الشخصية)
     */
    public function register(RegisterRequest $request): AuthResult
    {
        try {
            return DB::transaction(function () use ($request) {

                $userType = $this->determineUserType($request);

                // إنشاء المستخدم
                $user = $this->createUser($request, $userType);

                // ✅ رفع الصورة الشخصية إذا وجدت
                if ($request->hasFile('avatar')) {
                    $this->uploadAvatar($user, $request->file('avatar'));
                }

                // إذا كان صاحب صالون، أنشئ الصالون
                if ($userType === 'salon_owner') {
                    $this->createSalonForOwner($request, $user);
                }

                // تعيين الدور
                $this->assignRole($user, $userType);

                // تحميل العلاقات
                $this->loadUserRelations($user);

                // إنشاء توكن
                $token = $this->generateToken($user);

                // تجهيز بيانات المستخدم
                $userData = $this->formatUserData($user, $token);

                // رسالة نجاح حسب الدور
                $message = $this->getSuccessMessage($user);

                return AuthResult::success($message, $userData, 201);
            });

        } catch (\Illuminate\Validation\ValidationException $e) {
            return AuthResult::error('خطأ في البيانات المدخلة', $e->errors(), 422);
        } catch (\Exception $e) {
            Log::error('Registration error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء التسجيل', config('app.debug') ? $e->getMessage() : null, 500);
        }
    }

    /**
     * ✅ رفع الصورة الشخصية
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
     * ✅ توليد اسم فريد للصورة الشخصية
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
            //'role' => $primaryRole,
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
     * إنشاء مستخدم جديد
     */
    private function createUser(RegisterRequest $request, string $userType): User
    {
        $name = $userType === 'salon_owner' ? $request->owner_name : $request->name;

        return User::create([
            'name' => $name,
            'phone' => $request->phone,

            'password' => Hash::make($request->password),
            'role' => $userType,
            'is_active' => true,
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
}
