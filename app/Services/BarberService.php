<?php
// app/Services/Barber/BarberService.php

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
     * إضافة حلاق جديد مع أيام وساعات العمل
     */
    public function addBarber(array $data, User $salonOwner): AuthResult
    {
        try {
            return DB::transaction(function () use ($data, $salonOwner) {

                // التحقق من أن الصالونات المختارة تخص صاحب الصالون
                $this->validateSalonOwnership($data['salon_ids'] ?? [$salonOwner->ownedSalon->id], $salonOwner);

                // إنشاء المستخدم (الحلاق)
                $barber = User::create([
                    'name' => $data['full_name'],
                    'phone' => $data['phone'],
                    'email' => $data['email'] ?? null,
                    'password' => Hash::make($data['password']),
                    'role' => 'barber',
                    'is_active' => true,
                ]);

                // تعيين دور الحلاق
                $barber->assignRole('barber');

                // ربط الحلاق بالصالونات
                $salonIds = $data['salon_ids'] ?? [$salonOwner->ownedSalon->id];
                $barber->salons()->attach($salonIds, [
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // إضافة أيام وساعات العمل
                $this->addWorkingHours($barber, $data);

                // إضافة التخصص (يمكن تخزينه في جدول منفصل)
                $barber->barberProfile()->create([
                    'specialization' => $data['specialization'],
                ]);

                Log::info('Barber added', [
                    'barber_id' => $barber->id,
                    'added_by' => $salonOwner->id,
                    'working_days' => $data['working_days']
                ]);

                // تحميل العلاقات
                $barber->load(['salons', 'workingHours']);

                return AuthResult::success(
                    'تم إضافة الحلاق بنجاح',
                    $barber,
                    201
                );

            });
        } catch (\Exception $e) {
            Log::error('Add barber error: ' . $e->getMessage());

            return AuthResult::error(
                'حدث خطأ أثناء إضافة الحلاق',
                config('app.debug') ? $e->getMessage() : null,
                500
            );
        }
    }

    /**
     * إضافة أوقات العمل للحلاق
     */
    private function addWorkingHours(User $barber, array $data): void
    {
        // حذف الأوقات القديمة إذا وجدت
        $barber->workingHours()->delete();

        // إضافة ساعات العمل لكل يوم
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

        // إضافة أوقات الراحة إذا وجدت
        // if (isset($data['break_hours'])) {
        //     foreach ($data['break_hours'] as $break) {
        //         // يمكن إضافة جدول منفصل للراحات
        //         // أو تحديث سجل working_hours بإضافة break_start و break_end
        //     }
        // }

        // إضافة أيام الإجازة (الأيام غير المحددة في working_days)
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
    }

    private function validateSalonOwnership(array $salonIds, User $salonOwner): void
    {
        $ownedSalonIds = $salonOwner->ownedSalon()->pluck('id')->toArray();

        foreach ($salonIds as $salonId) {
            if (!in_array($salonId, $ownedSalonIds)) {
                throw new \Exception("الصالون رقم {$salonId} لا يتبع لك");
            }
        }
    }
}
