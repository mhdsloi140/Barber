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

class RegisterService
{
    /**
     * تسجيل صاحب صالون جديد مع صور متعددة وصورة شخصية
     */
    public function registerSalonOwner(array $data, ?array $images = null, ?UploadedFile $avatar = null): AuthResult
    {
        try {
            return DB::transaction(function () use ($data, $images, $avatar) {

                // 1. إنشاء المستخدم
                $user = User::create([
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                    'password' => Hash::make($data['password']),
                    'role' => 'salon_owner',
                    'is_active' => true,
                ]);

                // رفع الصورة الشخصية إذا وجدت
                if ($avatar) {
                    $this->uploadAvatar($user, $avatar);
                }

                // 2. تعيين الأدوار
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

                // 4. رفع صور الصالون
                if ($images && is_array($images)) {
                    $this->uploadMultipleImages($salon, $images);
                }

                // 5. إضافة أوقات العمل للصالون (فترة واحدة فقط)
                if (isset($data['working_hours']) && !empty($data['working_hours'])) {
                    $this->saveWorkingHours($salon, $data['working_hours']);
                }

                // 6. إذا كان يعمل كحلاق، أضفه للصالون
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
                        'avatar' => $user->getAvatarUrlAttribute(),
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
     * تعيين الأدوار للمستخدم
     */
    private function assignRoles(User $user, array $data): void
    {
        $user->assignRole('salon_owner');

        if (!empty($data['works_as_barber'])) {
            $user->assignRole('barber');
            Log::info('User assigned as barber too', ['user_id' => $user->id]);
        }
    }

    /**
     * إضافة الحلاق إلى الصالون
     */
    private function addBarberToSalon(User $user, Salon $salon, array $data): void
    {
        $user->salons()->attach($salon->id, [
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // إضافة أوقات عمل الحلاق إذا تم إرسالها (فترة واحدة فقط)
        if (isset($data['barber_working_hours']) && !empty($data['barber_working_hours'])) {
            $this->createBarberWorkingHours($user, $data);
        }
    }

    /**
     * إنشاء أوقات عمل للحلاق (فترة واحدة فقط)
     */
    private function createBarberWorkingHours(User $barber, array $data): void
    {
        if (isset($data['barber_working_hours']) && !empty($data['barber_working_hours'])) {
            foreach ($data['barber_working_hours'] as $hours) {
                WorkingHour::create([
                    'workable_type' => User::class,
                    'workable_id' => $barber->id,
                    'day_of_week' => $hours['day'],
                    'is_open' => $hours['is_open'],
                    'shift1_start' => $hours['start'] ?? null,
                    'shift1_end' => $hours['end'] ?? null,
                    // shift2_start, shift2_end, break_start, break_end = null
                ]);
            }
        }
    }

    /**
     * رفع صور متعددة للصالون
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
     * حفظ أوقات عمل الصالون (فترة واحدة فقط)
     */
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
                // shift2_start, shift2_end, break_start, break_end = null
            ]);
        }
    }

    /**
     * الحصول على أوقات العمل بصيغة منسقة (فترة واحدة فقط)
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
