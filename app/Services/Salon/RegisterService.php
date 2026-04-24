<?php
// app/Services/Salon/RegisterService.php

namespace App\Services\Salon;

use App\Models\User;
use App\Models\Salon;
use App\Models\WorkingHour;
use App\Services\AuthResult;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;

class RegisterService
{
    /**
     * تسجيل صاحب صالون جديد مع صور متعددة وأوقات عمل
     */
    public function registerSalonOwner(array $data, ?array $images = null): AuthResult
    {
        try {
            return DB::transaction(function () use ($data, $images) {

                // 1. إنشاء المستخدم
                $user = User::create([
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                    'password' => Hash::make($data['password']),
                    'role' => 'salon_owner', // الدور الأساسي
                    'is_active' => true,
                ]);

                // 2. تعيين الأدوار (salon_owner + barber إذا كان يعمل كحلاق)
                $this->assignRoles($user, $data);

                // 3. إنشاء الصالون
                $salon = Salon::create([
                    'name' => $data['salon_name'],
                    'owner_id' => $user->id,
                    'address' => $data['salon_address'],
                    'phone' => $data['salon_phone'] ?? $data['phone'],
                    'latitude' => $data['latitude'] ?? null,
                    'longitude' => $data['longitude'] ?? null,
                    'is_active' => true,
                ]);

                // 4. رفع الصور
                if ($images && is_array($images)) {
                    $this->uploadMultipleImages($salon, $images);
                }

                // 5. إضافة أوقات العمل للصالون
                if (isset($data['working_hours']) && !empty($data['working_hours'])) {
                    $this->saveWorkingHours($salon, $data['working_hours']);
                } else {
                    $this->createDefaultWorkingHours($salon);
                }

                
                if (!empty($data['works_as_barber'])) {
                    $this->addBarberToSalon($user, $salon, $data);
                }

                $result = [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'phone' => $user->phone,
                        'role' => $user->role,
                        'roles' => $user->getRoleNames(),
                        'works_as_barber' => !empty($data['works_as_barber']),
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
                    ],
                ];

                return AuthResult::success('تم إنشاء حساب الصالون بنجاح', $result, 201);

            });
        } catch (\Exception $e) {
            Log::error('Register salon owner error: ' . $e->getMessage());

            return AuthResult::error(
                'حدث خطأ أثناء إنشاء الحساب: ' . $e->getMessage(),
                null,
                500
            );
        }
    }


    private function assignRoles(User $user, array $data): void
    {
        // تعيين دور صاحب الصالون
        $user->assignRole('salon_owner');

        //  إذا كان يعمل كحلاق، أضف دور الحلاق أيضاً
        if (!empty($data['works_as_barber'])) {
            $user->assignRole('barber');
            Log::info('User assigned as barber too', ['user_id' => $user->id]);
        }
    }

    /**
     *  إضافة الحلاق إلى الصالون (ربط في جدول barber_salon)
     */
    private function addBarberToSalon(User $user, Salon $salon, array $data): void
    {
        // ربط الحلاق بالصالون
        $user->salons()->attach($salon->id, [
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        //  إضافة أوقات عمل خاصة بالحلاق (يمكن استخدام نفس أوقات الصالون أو مختلفة)
        $this->createBarberWorkingHours($user, $data);

        Log::info('Barber added to salon', [
            'barber_id' => $user->id,
            'salon_id' => $salon->id
        ]);
    }


    private function createBarberWorkingHours(User $barber, array $data): void
    {
        // إذا أرسل أوقات عمل خاصة بالحلاق
        if (isset($data['barber_working_hours']) && !empty($data['barber_working_hours'])) {
            foreach ($data['barber_working_hours'] as $hours) {
                WorkingHour::create([
                    'workable_type' => User::class,
                    'workable_id' => $barber->id,
                    'day_of_week' => $hours['day'],
                    'is_open' => $hours['is_open'],
                    'shift1_start' => $hours['shift1_start'] ?? null,
                    'shift1_end' => $hours['shift1_end'] ?? null,
                    'shift2_start' => $hours['shift2_start'] ?? null,
                    'shift2_end' => $hours['shift2_end'] ?? null,
                    'break_start' => $hours['break_start'] ?? null,
                    'break_end' => $hours['break_end'] ?? null,
                ]);
            }
        } else {
            // استخدام أوقات عمل افتراضية للحلاق
            $days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
            foreach ($days as $day) {
                if (in_array($day, ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday'])) {
                    WorkingHour::create([
                        'workable_type' => User::class,
                        'workable_id' => $barber->id,
                        'day_of_week' => $day,
                        'is_open' => true,
                        'shift1_start' => '09:00',
                        'shift1_end' => '22:00',
                    ]);
                } elseif ($day === 'friday') {
                    WorkingHour::create([
                        'workable_type' => User::class,
                        'workable_id' => $barber->id,
                        'day_of_week' => $day,
                        'is_open' => false,
                    ]);
                } else {
                    WorkingHour::create([
                        'workable_type' => User::class,
                        'workable_id' => $barber->id,
                        'day_of_week' => $day,
                        'is_open' => true,
                        'shift1_start' => '10:00',
                        'shift1_end' => '18:00',
                    ]);
                }
            }
        }
    }

    /**
     * رفع عدة صور للصالون
     */
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

    /**
     * حفظ أوقات العمل المرسلة من المستخدم
     */
    private function saveWorkingHours(Salon $salon, array $workingHours): void
    {
        foreach ($workingHours as $hours) {
            WorkingHour::create([
                'workable_type' => Salon::class,
                'workable_id' => $salon->id,
                'day_of_week' => $hours['day'],
                'is_open' => $hours['is_open'],
                'shift1_start' => $hours['shift1_start'] ?? null,
                'shift1_end' => $hours['shift1_end'] ?? null,
                'shift2_start' => $hours['shift2_start'] ?? null,
                'shift2_end' => $hours['shift2_end'] ?? null,
                'break_start' => $hours['break_start'] ?? null,
                'break_end' => $hours['break_end'] ?? null,
            ]);
        }
    }

    /**
     * إنشاء أوقات عمل افتراضية للصالون
     */
    private function createDefaultWorkingHours(Salon $salon): void
    {
        $days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

        foreach ($days as $day) {
            if (in_array($day, ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday'])) {
                WorkingHour::create([
                    'workable_type' => Salon::class,
                    'workable_id' => $salon->id,
                    'day_of_week' => $day,
                    'is_open' => true,
                    'shift1_start' => '09:00',
                    'shift1_end' => '22:00',
                ]);
            } elseif ($day === 'friday') {
                WorkingHour::create([
                    'workable_type' => Salon::class,
                    'workable_id' => $salon->id,
                    'day_of_week' => $day,
                    'is_open' => false,
                ]);
            } else {
                WorkingHour::create([
                    'workable_type' => Salon::class,
                    'workable_id' => $salon->id,
                    'day_of_week' => $day,
                    'is_open' => true,
                    'shift1_start' => '10:00',
                    'shift1_end' => '18:00',
                ]);
            }
        }
    }

    /**
     * تنسيق أوقات العمل للعرض
     */
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
                'morning' => $hour->shift1_start && $hour->shift1_end
                    ? $hour->shift1_start . ' - ' . $hour->shift1_end
                    : null,
                'evening' => $hour->shift2_start && $hour->shift2_end
                    ? $hour->shift2_start . ' - ' . $hour->shift2_end
                    : null,
                'break' => $hour->break_start && $hour->break_end
                    ? $hour->break_start . ' - ' . $hour->break_end
                    : null,
            ];
        }
        return $result;
    }
}
