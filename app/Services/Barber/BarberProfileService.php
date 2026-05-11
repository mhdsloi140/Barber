<?php
// app/Services/Barber/BarberProfileService.php

namespace App\Services\Barber;

use App\Models\User;
use App\Models\Salon;
use App\Models\WorkingHour;
use App\Services\AuthResult;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class BarberProfileService
{
    // أيام الأسبوع بالعربية
    private $daysInArabic = [
        'sunday' => 'الأحد',
        'monday' => 'الإثنين',
        'tuesday' => 'الثلاثاء',
        'wednesday' => 'الأربعاء',
        'thursday' => 'الخميس',
        'friday' => 'الجمعة',
        'saturday' => 'السبت',
    ];

    /**
     * عرض الملف الشخصي للحلاق (الاسم، رقم الهاتف، أوقات العمل)
     */
 
    public function getProfile(User $barber): AuthResult
    {
        try {
            if (!$barber->hasRole('barber')) {
                return AuthResult::error('هذه الخدمة متاحة للحلاقين فقط', null, 403);
            }

            // جلب أوقات العمل (الأيام المفتوحة فقط)
            $workingHours = $barber->workingHours()
                ->where('is_open', true)
                ->orderByRaw("FIELD(day_of_week, 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday')")
                ->get();

            $formattedWorkingHours = [];
            foreach ($workingHours as $hour) {
                $formattedWorkingHours[] = [
                    'day' => $hour->day_of_week,
                    'day_ar' => $this->daysInArabic[$hour->day_of_week],
                    'start' => $hour->shift1_start,
                    'end' => $hour->shift1_end,
                    'hours_text' => $hour->shift1_start . ' - ' . $hour->shift1_end,
                ];
            }

            //  جلب تقييمات الحلاق
            $ratings = $barber->ratingsReceived()
                ->where('is_approved', true)
                ->get();

            $totalRatings = $ratings->count();
            $averageRating = $totalRatings > 0 ? round($ratings->avg('rating'), 1) : 0;

            // توزيع التقييمات (كم تقييم 5 نجوم، 4 نجوم، إلخ)
            $distribution = [
                5 => $ratings->where('rating', 5)->count(),
                4 => $ratings->where('rating', 4)->count(),
                3 => $ratings->where('rating', 3)->count(),
                2 => $ratings->where('rating', 2)->count(),
                1 => $ratings->where('rating', 1)->count(),
            ];

            // آخر 5 تقييمات
            $recentRatings = $ratings->sortByDesc('created_at')
                ->take(5)
                ->map(function ($rating) {
                    return [
                        'id' => $rating->id,
                        'customer_name' => $rating->customer->name ?? 'عميل',
                        'rating' => $rating->rating,
                        'comment' => $rating->comment,
                        'created_at' => $rating->created_at->diffForHumans(),
                    ];
                })->values();

            $data = [
                'id' => $barber->id,
                'name' => $barber->name,
                'phone' => $barber->phone,
                'avatar' => $barber->getAvatarUrlAttribute(),
                'is_active' => $barber->is_active,
                'working_hours' => $formattedWorkingHours,
                'created_at' => $barber->created_at,

                //  معلومات التقييم
                'rating' => [
                    'average' => $averageRating,
                    'total' => $totalRatings,
                    'distribution' => $distribution,
                    'recent' => $recentRatings,
                ],
            ];

            return AuthResult::success('تم جلب الملف الشخصي بنجاح', $data);

        } catch (\Exception $e) {
            Log::error('Get barber profile error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب الملف الشخصي', null, 500);
        }
    }
    /**
     * تحديث الملف الشخصي للحلاق (الاسم، رقم الهاتف، أوقات العمل)
     */
    public function updateProfile(User $barber, array $data): AuthResult
    {
        try {
            return DB::transaction(function () use ($barber, $data) {

                if (!$barber->hasRole('barber')) {
                    return AuthResult::error('هذه الخدمة متاحة للحلاقين فقط', null, 403);
                }

                // جلب الصالون التابع للحلاق
                $salon = $barber->salons()->first();

                if (!$salon) {
                    return AuthResult::error('لا يوجد صالون تابع لك', null, 404);
                }

                // 1. تحديث الاسم
                if (isset($data['name'])) {
                    $barber->name = $data['name'];
                }

                // 2. تحديث رقم الهاتف
                if (isset($data['phone'])) {
                    $exists = User::where('phone', $data['phone'])
                        ->where('id', '!=', $barber->id)
                        ->exists();

                    if ($exists) {
                        return AuthResult::error('رقم الهاتف مستخدم بالفعل', null, 400);
                    }
                    $barber->phone = $data['phone'];
                }

                // 3. تحديث الصورة الشخصية (باستخدام Spatie Media Library)
                if (isset($data['avatar']) && $data['avatar'] instanceof UploadedFile) {
                    $this->updateAvatar($barber, $data['avatar']);
                }

                // 4. تحديث كلمة المرور
                if (isset($data['password']) && !empty($data['password'])) {
                    $barber->password = Hash::make($data['password']);
                }

                $barber->save();

                // 5. تحديث أوقات العمل مع التحقق من فتح الصالون
                if (isset($data['working_hours']) && !empty($data['working_hours'])) {
                    $validationResult = $this->validateWorkingHoursAgainstSalon($barber, $salon, $data['working_hours']);

                    if (!$validationResult['valid']) {
                        return AuthResult::error($validationResult['message'], null, 400);
                    }

                    $this->updateWorkingHours($barber, $data['working_hours']);
                }

                $barber->refresh();

                // جلب أوقات العمل المحدثة
                $workingHours = $barber->workingHours()
                    ->where('is_open', true)
                    ->orderByRaw("FIELD(day_of_week, 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday')")
                    ->get();

                $formattedWorkingHours = [];
                foreach ($workingHours as $hour) {
                    $formattedWorkingHours[] = [
                        'day' => $hour->day_of_week,
                        'day_ar' => $this->daysInArabic[$hour->day_of_week],
                        'start' => $hour->shift1_start,
                        'end' => $hour->shift1_end,
                        'hours_text' => $hour->shift1_start . ' - ' . $hour->shift1_end,
                    ];
                }

                return AuthResult::success('تم تحديث الملف الشخصي بنجاح', [
                    'id' => $barber->id,
                    'name' => $barber->name,
                    'phone' => $barber->phone,
                    'avatar' => $barber->getAvatarUrlAttribute(),
                    'working_hours' => $formattedWorkingHours,
                ]);

            });
        } catch (\Exception $e) {
            Log::error('Update barber profile error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء تحديث الملف الشخصي: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * تحديث الصورة الشخصية باستخدام Spatie Media Library
     */
    private function updateAvatar(User $user, UploadedFile $avatar): void
    {
        try {
            // التحقق من وجود الصورة
            if (!$avatar->isValid()) {
                Log::error('Invalid avatar file');
                return;
            }

            // حذف الصورة القديمة إذا وجدت
            if ($user->getFirstMedia('avatar')) {
                $user->clearMediaCollection('avatar');
            }

            // إضافة الصورة الجديدة
            $user->addMedia($avatar)
                ->usingFileName($this->generateFileName($avatar))
                ->toMediaCollection('avatar');

            Log::info('Avatar updated successfully', [
                'user_id' => $user->id,
                'file_name' => $avatar->getClientOriginalName()
            ]);

        } catch (\Exception $e) {
            Log::error('Avatar update failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * توليد اسم فريد للملف
     */
    private function generateFileName(UploadedFile $file): string
    {
        return 'avatar_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
    }

    /**
     * التحقق من أن أوقات العمل المطلوبة تقع ضمن أوقات عمل الصالون
     */
    private function validateWorkingHoursAgainstSalon(User $barber, Salon $salon, array $workingHours): array
    {
        $errors = [];

        // جلب أوقات عمل الصالون
        $salonWorkingHours = $salon->workingHours()
            ->get()
            ->keyBy('day_of_week');

        foreach ($workingHours as $hours) {
            $day = $hours['day'];
            $dayName = $this->daysInArabic[$day];
            $isOpen = $hours['is_open'] ?? false;

            if (!$isOpen) {
                continue;
            }

            if (!$salonWorkingHours->has($day)) {
                $errors[] = "اليوم {$dayName} غير موجود في جدول عمل الصالون";
                continue;
            }

            $salonHour = $salonWorkingHours->get($day);

            if (!$salonHour->is_open) {
                $errors[] = "الصالون مغلق في يوم {$dayName}، لا يمكنك إضافة مواعيد في هذا اليوم";
                continue;
            }

            // استخراج الوقت من الصالون (إزالة الثواني إذا كانت موجودة)
            $salonStart = substr($salonHour->shift1_start, 0, 5);
            $salonEnd = substr($salonHour->shift1_end, 0, 5);

            $requestedStart = $hours['start'] ?? null;
            $requestedEnd = $hours['end'] ?? null;

            // تنظيف الوقت المرسل
            $requestedStart = $requestedStart ? substr($requestedStart, 0, 5) : null;
            $requestedEnd = $requestedEnd ? substr($requestedEnd, 0, 5) : null;

            if (!$requestedStart || !$requestedEnd) {
                $errors[] = "يجب تحديد وقت البدء والنهاية ليوم {$dayName}";
                continue;
            }

            // المقارنة
            if ($requestedStart < $salonStart || $requestedEnd > $salonEnd) {
                $errors[] = "وقت العمل ليوم {$dayName} ({$requestedStart} - {$requestedEnd}) خارج أوقات عمل الصالون ({$salonStart} - {$salonEnd})";
            }

            if ($requestedStart >= $requestedEnd) {
                $errors[] = "وقت البدء يجب أن يكون قبل وقت النهاية ليوم {$dayName}";
            }
        }

        if (!empty($errors)) {
            return ['valid' => false, 'message' => implode('، ', $errors)];
        }

        return ['valid' => true, 'message' => ''];
    }

    /**
     * تحديث أوقات العمل
     */
    private function updateWorkingHours(User $barber, array $workingHours): void
    {
        // حذف الأوقات القديمة
        $barber->workingHours()->delete();

        foreach ($workingHours as $hours) {
            $isOpen = $hours['is_open'] ?? false;

            if ($isOpen && isset($hours['start']) && isset($hours['end'])) {
                WorkingHour::create([
                    'workable_type' => User::class,
                    'workable_id' => $barber->id,
                    'day_of_week' => $hours['day'],
                    'is_open' => true,
                    'shift1_start' => $hours['start'],
                    'shift1_end' => $hours['end'],
                ]);
            }
        }
    }

    /**
     * جلب الملف الشخصي الكامل للحلاق
     */
    public function getBarberProfile(User $barber): AuthResult
    {
        try {
            if (!$barber->hasRole('barber')) {
                return AuthResult::error('هذه الخدمة متاحة للحلاقين فقط', null, 403);
            }

            $workingHours = $barber->workingHours()
                ->where('is_open', true)
                ->orderByRaw("FIELD(day_of_week, 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday')")
                ->get();

            $formattedWorkingHours = [];
            foreach ($workingHours as $hour) {
                $formattedWorkingHours[] = [
                    'day' => $hour->day_of_week,
                    'day_ar' => $this->daysInArabic[$hour->day_of_week],
                    'start' => $hour->shift1_start,
                    'end' => $hour->shift1_end,
                    'hours_text' => $hour->shift1_start . ' - ' . $hour->shift1_end,
                ];
            }

            $data = [
                'id' => $barber->id,
                'name' => $barber->name,
                'phone' => $barber->phone,
                'avatar' => $barber->getAvatarUrlAttribute(),
                'working_hours' => $formattedWorkingHours,
            ];

            return AuthResult::success('تم جلب الملف الشخصي بنجاح', $data);

        } catch (\Exception $e) {
            Log::error('Get barber profile error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب الملف الشخصي', null, 500);
        }
    }

    /**
     * تحديث بيانات الحلاق الأساسية (الاسم، الهاتف)
     */
    public function updateBarberBasicInfo(User $barber, array $data): AuthResult
    {
        try {
            $updateData = [];

            if (isset($data['name'])) {
                $updateData['name'] = $data['name'];
            }

            if (isset($data['phone'])) {
                $exists = User::where('phone', $data['phone'])
                    ->where('id', '!=', $barber->id)
                    ->exists();

                if ($exists) {
                    return AuthResult::error('رقم الهاتف مستخدم بالفعل', null, 400);
                }
                $updateData['phone'] = $data['phone'];
            }

            if (isset($data['avatar']) && $data['avatar'] instanceof UploadedFile) {
                $this->updateAvatar($barber, $data['avatar']);
            }

            if (!empty($updateData)) {
                $barber->update($updateData);
            }

            return AuthResult::success('تم تحديث البيانات بنجاح', [
                'id' => $barber->id,
                'name' => $barber->name,
                'phone' => $barber->phone,
                'avatar' => $barber->getAvatarUrlAttribute(),
            ]);

        } catch (\Exception $e) {
            Log::error('Update barber basic info error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء تحديث البيانات', null, 500);
        }
    }
}
