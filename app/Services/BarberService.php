<?php
// app/Services/BarberService.php

namespace App\Services;

use App\Models\User;
use App\Models\WorkingHour;
use App\Services\AuthResult;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BarberService
{
    /**
     * إضافة حلاق جديد (بدون أوقات عمل)
     */
    public function addBarber(array $data, User $salonOwner): AuthResult
    {
        try {
            return DB::transaction(function () use ($data, $salonOwner) {

                // التحقق من أن الصالون يتبع صاحب الصالون
                $salon = $salonOwner->ownedSalon;
                if (!$salon) {
                    return AuthResult::error('لا يوجد صالون تابع لك', null, 404);
                }

                $barber = User::create([
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                    'password' => Hash::make('password'), // كلمة مرور مؤقتة
                    'role' => 'barber',
                    'is_active' => true,
                ]);

                // تعيين دور الحلاق
                $barber->assignRole('barber');

                // ربط الحلاق بالصالون
                $barber->salons()->attach($salon->id, [
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                Log::info('Barber added', [
                    'barber_id' => $barber->id,
                    'added_by' => $salonOwner->id,
                    'phone' => $barber->phone
                ]);

                // تحميل العلاقات
                $barber->load(['salons']);

                return AuthResult::success(
                    'تم اضافة الحلاق بنجاح',
                    [
                        'barber' => [
                            'id' => $barber->id,
                            'name' => $barber->name,
                            'phone' => $barber->phone,
                            'role' => $barber->role,
                            'is_active' => $barber->is_active
                        ]
                    ],
                    201
                );

            });
        } catch (\Exception $e) {
            Log::error('Add barber error: ' . $e->getMessage());

            return AuthResult::error(
                'حدث خطأ أثناء إضافة الحلاق: ' . $e->getMessage(),
                null,
                500
            );
        }
    }

    /**
     * جلب كل الحلاقين التابعين لصاحب الصالون
     */
    public function getBarbers(User $salonOwner): AuthResult
    {
        try {
            $salon = $salonOwner->ownedSalon;

            if (!$salon) {
                return AuthResult::error('لا يوجد صالون تابع لك', null, 404);
            }

            // تحميل العلاقات الموجودة فقط
            $barbers = $salon->barbers()
                ->with(['salons'])
                ->get();

            return AuthResult::success(
                'تم جلب الحلاقين بنجاح',
                $barbers
            );

        } catch (\Exception $e) {
            Log::error('Get barbers error: ' . $e->getMessage());

            return AuthResult::error(
                'حدث خطأ أثناء جلب الحلاقين: ' . $e->getMessage(),
                null,
                500
            );
        }
    }

    /**
     * جلب بيانات حلاق معين
     */
    public function getBarber(User $salonOwner, int $barberId): AuthResult
    {
        try {
            $salon = $salonOwner->ownedSalon;

            if (!$salon) {
                return AuthResult::error('لا يوجد صالون تابع لك', null, 404);
            }

            $barber = $salon->barbers()
                ->with(['salons'])
                ->where('users.id', $barberId)
                ->first();

            if (!$barber) {
                return AuthResult::error('الحلاق غير موجود', null, 404);
            }

            return AuthResult::success(
                'تم جلب بيانات الحلاق بنجاح',
                $barber
            );

        } catch (\Exception $e) {
            Log::error('Get barber error: ' . $e->getMessage());

            return AuthResult::error(
                'حدث خطأ أثناء جلب بيانات الحلاق',
                config('app.debug') ? $e->getMessage() : null,
                500
            );
        }
    }

    /**
     * تحديث بيانات حلاق
     */
    public function updateBarber(User $salonOwner, int $barberId, array $data): AuthResult
    {
        try {
            return DB::transaction(function () use ($salonOwner, $barberId, $data) {

                $salon = $salonOwner->ownedSalon;

                if (!$salon) {
                    return AuthResult::error('لا يوجد صالون تابع لك', null, 404);
                }

                $barber = $salon->barbers()->where('users.id', $barberId)->first();

                if (!$barber) {
                    return AuthResult::error('الحلاق غير موجود', null, 404);
                }

                // تحديث البيانات الأساسية
                if (isset($data['name'])) {
                    $barber->name = $data['name'];
                }
                if (isset($data['phone'])) {
                    // التحقق من عدم تكرار رقم الهاتف
                    $existingUser = User::where('phone', $data['phone'])
                        ->where('id', '!=', $barberId)
                        ->first();
                    if ($existingUser) {
                        return AuthResult::error('رقم الهاتف مستخدم بالفعل', null, 422);
                    }
                    $barber->phone = $data['phone'];
                }
                $barber->save();

                Log::info('Barber updated', [
                    'barber_id' => $barber->id,
                    'updated_by' => $salonOwner->id
                ]);

                return AuthResult::success(
                    'تم تحديث بيانات الحلاق بنجاح',
                    $barber->fresh(['salons'])
                );
            });

        } catch (\Exception $e) {
            Log::error('Update barber error: ' . $e->getMessage());

            return AuthResult::error(
                'حدث خطأ أثناء تحديث بيانات الحلاق',
                config('app.debug') ? $e->getMessage() : null,
                500
            );
        }
    }

    /**
     * إيقاف حلاق مؤقتاً (تعطيل الحساب)
     * تغيير is_active من true إلى false
     */
    public function deactivateBarber(User $salonOwner, int $barberId): AuthResult
    {
        try {
            $salon = $salonOwner->ownedSalon;

            if (!$salon) {
                return AuthResult::error('لا يوجد صالون تابع لك', null, 404);
            }

            $barber = $salon->barbers()->where('users.id', $barberId)->first();

            if (!$barber) {
                return AuthResult::error('الحلاق غير موجود', null, 404);
            }

            // التحقق من أنه ليس موقوفاً بالفعل
            if (!$barber->is_active) {
                return AuthResult::error('الحلاق بالفعل موقوف', null, 400);
            }

            // تغيير الحالة إلى false (غير نشط)
            $barber->is_active = false;
            $barber->save();

            Log::info('Barber deactivated', [
                'barber_id' => $barberId,
                'deactivated_by' => $salonOwner->id
            ]);

            return AuthResult::success(
                'تم إيقاف الحلاق بنجاح',
                [
                    'barber_id' => $barberId,
                    'name' => $barber->name,
                    'is_active' => false
                ]
            );

        } catch (\Exception $e) {
            Log::error('Deactivate barber error: ' . $e->getMessage());

            return AuthResult::error(
                'حدث خطأ أثناء إيقاف الحلاق',
                config('app.debug') ? $e->getMessage() : null,
                500
            );
        }
    }

    public function activateBarber(User $salonOwner, int $barberId): AuthResult
    {
        try {
            $salon = $salonOwner->ownedSalon;

            if (!$salon) {
                return AuthResult::error('لا يوجد صالون تابع لك', null, 404);
            }

            $barber = $salon->barbers()->where('users.id', $barberId)->first();

            if (!$barber) {
                return AuthResult::error('الحلاق غير موجود', null, 404);
            }

            // التحقق من أنه ليس مفعلاً بالفعل
            if ($barber->is_active) {
                return AuthResult::error('الحلاق بالفعل مفعل', null, 400);
            }

            // تغيير الحالة إلى true (نشط)
            $barber->is_active = true;
            $barber->save();



            return AuthResult::success(
                'تم تفعيل الحلاق بنجاح',
                [
                    'barber_id' => $barberId,
                    'name' => $barber->name,
                    'is_active' => true
                ]
            );

        } catch (\Exception $e) {
            Log::error('Activate barber error: ' . $e->getMessage());

            return AuthResult::error(
                'حدث خطأ أثناء تفعيل الحلاق',
                config('app.debug') ? $e->getMessage() : null,
                500
            );
        }
    }

    public function toggleBarberStatus(User $salonOwner, int $barberId): AuthResult
    {
        try {
            $salon = $salonOwner->ownedSalon;

            if (!$salon) {
                return AuthResult::error('لا يوجد صالون تابع لك', null, 404);
            }

            $barber = $salon->barbers()->where('users.id', $barberId)->first();

            if (!$barber) {
                return AuthResult::error('الحلاق غير موجود', null, 404);
            }

            // عكس الحالة الحالية
            $oldStatus = $barber->is_active;
            $barber->is_active = !$barber->is_active;
            $barber->save();

            $statusText = $barber->is_active ? 'تفعيل' : 'إيقاف';


            return AuthResult::success(
                "تم {$statusText} الحلاق بنجاح",
                [
                    'barber_id' => $barberId,
                    'name' => $barber->name,
                    'is_active' => $barber->is_active
                ]
            );

        } catch (\Exception $e) {
            Log::error('Toggle barber status error: ' . $e->getMessage());

            return AuthResult::error(
                'حدث خطأ أثناء تغيير حالة الحلاق',
                config('app.debug') ? $e->getMessage() : null,
                500
            );
        }
    }

    /**
     * حذف حلاق (Soft Delete)
     */
    public function deleteBarber(User $salonOwner, int $barberId): AuthResult
    {
        try {
            $salon = $salonOwner->ownedSalon;

            if (!$salon) {
                return AuthResult::error('لا يوجد صالون تابع لك', null, 404);
            }

            $barber = $salon->barbers()->where('users.id', $barberId)->first();

            if (!$barber) {
                return AuthResult::error('الحلاق غير موجود', null, 404);
            }

            // حذف الحلاق (soft delete)
            $barber->delete();



            return AuthResult::success('تم حذف الحلاق بنجاح');

        } catch (\Exception $e) {
            Log::error('Delete barber error: ' . $e->getMessage());

            return AuthResult::error(
                'حدث خطأ أثناء حذف الحلاق',
                config('app.debug') ? $e->getMessage() : null,
                500
            );
        }
    }

    /**
     * إضافة أوقات العمل (يقوم بها الحلاق بعد تسجيل الدخول)
     */
    public function updateWorkingHours(User $barber, array $data): AuthResult
    {
        try {
            return DB::transaction(function () use ($barber, $data) {

                // التحقق من أن المستخدم هو بالفعل حلاق
                if (!$barber->hasRole('barber')) {
                    return AuthResult::error('هذا المستخدم ليس حلاقاً', null, 403);
                }

                // حذف الأوقات القديمة
                $barber->workingHours()->delete();

                // إضافة ساعات العمل الجديدة
                foreach ($data['working_hours'] as $hours) {
                    WorkingHour::create([
                        'workable_type' => User::class,
                        'workable_id' => $barber->id,
                        'day_of_week' => $hours['day'],
                        'is_open' => true,
                        'shift1_start' => $hours['start'],
                        'shift1_end' => $hours['end'],
                    ]);
                }

                // إضافة أيام الإجازة (الأيام غير المذكورة)
                $allDays = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
                $workingDays = array_column($data['working_hours'], 'day');

                foreach ($allDays as $day) {
                    if (!in_array($day, $workingDays)) {
                        WorkingHour::create([
                            'workable_type' => User::class,
                            'workable_id' => $barber->id,
                            'day_of_week' => $day,
                            'is_open' => false,
                        ]);
                    }
                }

                Log::info('Working hours updated', [
                    'barber_id' => $barber->id
                ]);

                return AuthResult::success(
                    'تم تحديث أوقات العمل بنجاح',
                    $barber->fresh('workingHours')
                );

            });
        } catch (\Exception $e) {
            Log::error('Update working hours error: ' . $e->getMessage());

            return AuthResult::error(
                'حدث خطأ أثناء تحديث أوقات العمل',
                config('app.debug') ? $e->getMessage() : null,
                500
            );
        }
    }

    /**
     * جلب أوقات العمل الحالية
     */
    public function getWorkingHours(User $barber): AuthResult
    {
        try {
            // التحقق من أن المستخدم هو بالفعل حلاق
            if (!$barber->hasRole('barber')) {
                return AuthResult::error('هذا المستخدم ليس حلاقاً', null, 403);
            }

            $workingHours = $barber->workingHours()
                ->orderByRaw("FIELD(day_of_week, 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday')")
                ->get();

            return AuthResult::success(
                'تم جلب أوقات العمل بنجاح',
                $workingHours
            );

        } catch (\Exception $e) {
            Log::error('Get working hours error: ' . $e->getMessage());

            return AuthResult::error(
                'حدث خطأ أثناء جلب أوقات العمل',
                config('app.debug') ? $e->getMessage() : null,
                500
            );
        }
    }
}
