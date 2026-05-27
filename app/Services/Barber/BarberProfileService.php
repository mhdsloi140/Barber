<?php
// app/Services/Barber/BarberProfileService.php

namespace App\Services\Barber;

use App\Models\User;
use App\Models\Salon;
use App\Models\Appointment;
use App\Models\WorkingHour;
use App\Services\AuthResult;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;
use Carbon\Carbon;

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


public function getProfile(User $barber): AuthResult
{
    try {
        if (!$barber->hasRole('barber')) {
            return AuthResult::error('هذه الخدمة متاحة للحلاقين فقط', null, 403);
        }

        $workingHours = $this->getFormattedWorkingHours($barber);
        $ratingInfo = $this->getBarberRatingInfo($barber);

        // 1. اختصاصات الحلاق الحالية
        $barberSpecializations = $barber->specializations()
            ->where('is_active', true)
            ->get()
            ->map(fn($spec) => [
                'id' => $spec->id,
                'name' => $spec->name,
                // 'name_ar' => $spec->name_ar ?? $spec->name,
                // 'icon' => $spec->icon,
                // 'description' => $spec->description,
                // 'is_selected' => true,
            ]);

        // 2. جميع الاختصاصات المتاحة في النظام
        $allSpecializations = \App\Models\Specialization::where('is_active', true)
            ->get()
            ->map(fn($spec) => [
                'id' => $spec->id,
                'name' => $spec->name,
                // 'name_ar' => $spec->name_ar ?? $spec->name,
                // 'icon' => $spec->icon,
                // 'description' => $spec->description,
                'is_active' => $spec->is_active,
                'is_selected' => $barber->specializations()->where('specialization_id', $spec->id)->exists(),
            ]);

        // 3. إحصائيات الاختصاصات
        $specializationsStats = [
            'total' => $allSpecializations->count(),
            'selected' => $barberSpecializations->count(),
            'remaining' => $allSpecializations->count() - $barberSpecializations->count(),
        ];

        $data = [
            'id' => $barber->id,
            'name' => $barber->name,
            'phone' => $barber->phone,
            'avatar' => $barber->getAvatarUrlAttribute(),
            'is_active' => $barber->is_active,
            'working_hours' => $workingHours,
            'created_at' => $barber->created_at,
            'rating' => $ratingInfo,
            'completed_appointments_this_month' => $this->getCurrentMonthCompletedAppointments($barber->id),

            // اختصاصات الحلاق الحالية
            'my_specializations' => $barberSpecializations,
            'my_specializations_count' => $barberSpecializations->count(),
            'my_specializations_text' => $barberSpecializations->pluck('name_ar')->implode('، '),
            'my_specializations_ids' => $barberSpecializations->pluck('id')->toArray(),

            // جميع الاختصاصات المتاحة
            'all_specializations' => $allSpecializations,
            'all_specializations_count' => $allSpecializations->count(),

            // إحصائيات الاختصاصات
            'specializations_stats' => $specializationsStats,
        ];

        return AuthResult::success('تم جلب الملف الشخصي بنجاح', $data);

    } catch (\Exception $e) {
        Log::error('Get barber profile error: ' . $e->getMessage());
        return AuthResult::error('حدث خطأ أثناء جلب الملف الشخصي', null, 500);
    }
}


    /**
     * جلب إحصائيات الحلاق كاملة (الخدمات المنجزة، التقييم، الصورة)
     */
    public function getBarberStatistics(User $barber): AuthResult
    {
        try {
            if (!$barber->hasRole('barber')) {
                return AuthResult::error('هذه الخدمة متاحة للحلاقين فقط', null, 403);
            }

            $currentMonth = now()->month;
            $currentYear = now()->year;

            $data = [
                'barber' => [
                    'id' => $barber->id,
                    'name' => $barber->name,
                    'phone' => $barber->phone,
                    'avatar' => $barber->getAvatarUrlAttribute(),
                    'is_active' => $barber->is_active,
                ],
                'rating' => $this->getBarberRatingInfo($barber),

                'completed_appointments_this_month' => $this->getCurrentMonthCompletedAppointments($barber->id),

            ];

            return AuthResult::success('تم جلب الإحصائيات بنجاح', $data);

        } catch (\Exception $e) {
            Log::error('Get barber statistics error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب الإحصائيات', null, 500);
        }
    }

    /**
     * جلب عدد الحجوزات المكتملة في الشهر الحالي فقط (بدون تفاصيل)
     */
    public function getCurrentMonthCompletedAppointmentsCount(User $barber): AuthResult
    {
        try {
            if (!$barber->hasRole('barber')) {
                return AuthResult::error('هذه الخدمة متاحة للحلاقين فقط', null, 403);
            }

            $count = $this->getCurrentMonthCompletedAppointments($barber->id);

            return AuthResult::success('تم جلب عدد الحجوزات المكتملة', [
                'completed_appointments' => $count,
            ]);

        } catch (\Exception $e) {
            Log::error('Get completed appointments count error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب البيانات', null, 500);
        }
    }

    // ======================
    // دوال تحديث الملف الشخصي
    // ======================

  /**
 * تحديث الملف الشخصي للحلاق
 */
public function updateProfile(User $barber, array $data): AuthResult
{
    try {
        return DB::transaction(function () use ($barber, $data) {

            if (!$barber->hasRole('barber')) {
                return AuthResult::error('هذه الخدمة متاحة للحلاقين فقط', null, 403);
            }

            $salon = $barber->salons()->first();
            if (!$salon) {
                return AuthResult::error('لا يوجد صالون تابع لك', null, 404);
            }

            // تحديث البيانات الأساسية
            $this->updateBasicInfo($barber, $data);

            // تحديث الصورة الشخصية
            if (isset($data['avatar']) && $data['avatar'] instanceof UploadedFile) {
                $this->updateAvatar($barber, $data['avatar']);
            }

            // تحديث كلمة المرور
            if (isset($data['password']) && !empty($data['password'])) {
                $barber->password = Hash::make($data['password']);
                $barber->save();
            }

            // تحديث أوقات العمل
            if (isset($data['working_hours']) && !empty($data['working_hours'])) {
                $validationResult = $this->validateWorkingHoursAgainstSalon($barber, $salon, $data['working_hours']);
                if (!$validationResult['valid']) {
                    return AuthResult::error($validationResult['message'], null, 400);
                }
                $this->updateWorkingHours($barber, $data['working_hours']);
            }

            // 🔴 تحديث الاختصاصات
            if (isset($data['specialization_ids']) && is_array($data['specialization_ids'])) {
                $this->updateSpecializations($barber, $data['specialization_ids']);
            }

            $barber->refresh();

            // جلب الاختصاصات المحدثة
            $specializations = $barber->specializations()
                ->where('is_active', true)
                ->get()
                ->map(fn($spec) => [
                    'id' => $spec->id,
                    'name' => $spec->name,
                    'name_ar' => $spec->name_ar ?? $spec->name,
                    'icon' => $spec->icon,
                ]);

            return AuthResult::success('تم تحديث الملف الشخصي بنجاح', [
                'id' => $barber->id,
                'name' => $barber->name,
                'phone' => $barber->phone,
                'avatar' => $barber->getAvatarUrlAttribute(),
                'working_hours' => $this->getFormattedWorkingHours($barber),
                'specializations' => $specializations,
                'specializations_count' => $specializations->count(),
                'specializations_text' => $specializations->pluck('name_ar')->implode('، '),
            ]);

        });
    } catch (\Exception $e) {
        Log::error('Update barber profile error: ' . $e->getMessage());
        return AuthResult::error('حدث خطأ أثناء تحديث الملف الشخصي: ' . $e->getMessage(), null, 500);
    }
}

/**
 * تحديث اختصاصات الحلاق
 */
private function updateSpecializations(User $barber, array $specializationIds): void
{
    // إزالة القيم المكررة
    $uniqueIds = array_unique($specializationIds);

    // التحقق من أن القيم أرقام صحيحة
    $validIds = array_filter($uniqueIds, fn($id) => is_numeric($id) && $id > 0);

    if (!empty($validIds)) {
        $barber->specializations()->sync($validIds);

        Log::info('Barber specializations updated', [
            'barber_id' => $barber->id,
            'specialization_ids' => $validIds,
        ]);
    }
}

    /**
     * تحديث بيانات الحلاق الأساسية (الاسم، الهاتف)
     */
    public function updateBarberBasicInfo(User $barber, array $data): AuthResult
    {
        try {
            $this->updateBasicInfo($barber, $data);

            if (isset($data['avatar']) && $data['avatar'] instanceof UploadedFile) {
                $this->updateAvatar($barber, $data['avatar']);
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


    private function getCurrentMonthCompletedAppointments(int $barberId): int
    {
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        return Appointment::where('barber_id', $barberId)
            ->where('status', 'completed')
            ->whereBetween('appointment_date', [$startOfMonth, $endOfMonth])
            ->count();
    }

    /**
     * عدد الحجوزات المكتملة في سنة كاملة (شهرياً)
     */
    private function getYearlyCompletedAppointments(int $barberId, int $year): array
    {
        $result = [];
        for ($month = 1; $month <= 12; $month++) {
            $startDate = Carbon::create($year, $month, 1)->startOfDay();
            $endDate = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();

            $count = Appointment::where('barber_id', $barberId)
                ->where('status', 'completed')
                ->whereBetween('appointment_date', [$startDate, $endDate])
                ->count();

            $result[] = $count;
        }
        return $result;
    }

    /**
     * تحديث البيانات الأساسية (الاسم، الهاتف)
     */
    private function updateBasicInfo(User $barber, array $data): void
    {
        if (isset($data['name'])) {
            $barber->name = $data['name'];
        }

        if (isset($data['phone'])) {
            $exists = User::where('phone', $data['phone'])
                ->where('id', '!=', $barber->id)
                ->exists();

            if (!$exists) {
                $barber->phone = $data['phone'];
            }
        }

        $barber->save();
    }

    /**
     * تحديث الصورة الشخصية باستخدام Spatie Media Library
     */
    private function updateAvatar(User $user, UploadedFile $avatar): void
    {
        try {
            if (!$avatar->isValid()) {
                Log::error('Invalid avatar file');
                return;
            }

            if ($user->getFirstMedia('avatar')) {
                $user->clearMediaCollection('avatar');
            }

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
     * تحديث أوقات العمل
     */
    private function updateWorkingHours(User $barber, array $workingHours): void
    {
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
     * الحصول على أوقات العمل المنسقة
     */
    private function getFormattedWorkingHours(User $barber): array
    {
        $workingHours = $barber->workingHours()
            ->where('is_open', true)
            ->orderByRaw("FIELD(day_of_week, 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday')")
            ->get();

        $formatted = [];
        foreach ($workingHours as $hour) {
            $formatted[] = [
                'day' => $hour->day_of_week,
                'day_ar' => $this->daysInArabic[$hour->day_of_week],
                'start' => $hour->shift1_start,
                'end' => $hour->shift1_end,
                'hours_text' => $hour->shift1_start . ' - ' . $hour->shift1_end,
            ];
        }

        return $formatted;
    }

    /**
     * الحصول على معلومات تقييم الحلاق
     */
    private function getBarberRatingInfo(User $barber): array
    {
        $ratings = $barber->ratingsReceived()
            ->where('is_approved', true)
            ->get();

        $totalRatings = $ratings->count();
        $averageRating = $totalRatings > 0 ? round($ratings->avg('rating'), 1) : 0;

        $distribution = [
            5 => $ratings->where('rating', 5)->count(),
            4 => $ratings->where('rating', 4)->count(),
            3 => $ratings->where('rating', 3)->count(),
            2 => $ratings->where('rating', 2)->count(),
            1 => $ratings->where('rating', 1)->count(),
        ];

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

        return [
            'average' => $averageRating,
            'total' => $totalRatings,
            'distribution' => $distribution,
            'recent' => $recentRatings,
        ];
    }

    /**
     * إحصائيات المواعيد
     */
    // private function getAppointmentStatistics(int $barberId): array
    // {
    //     $total = Appointment::where('barber_id', $barberId)->count();
    //     $completed = Appointment::where('barber_id', $barberId)->where('status', 'completed')->count();
    //     $pending = Appointment::where('barber_id', $barberId)->where('status', 'pending')->count();
    //     $cancelled = Appointment::where('barber_id', $barberId)->where('status', 'cancelled')->count();

    //     return [
    //         'total_appointments' => $total,
    //         'completed_appointments' => $completed,
    //         'pending_appointments' => $pending,
    //         'cancelled_appointments' => $cancelled,
    //         'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
    //     ];
    // }

    /**
     * التحقق من أن أوقات العمل ضمن أوقات عمل الصالون
     */
    private function validateWorkingHoursAgainstSalon(User $barber, Salon $salon, array $workingHours): array
    {
        $errors = [];
        $salonWorkingHours = $salon->workingHours()->get()->keyBy('day_of_week');

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

            $salonStart = substr($salonHour->shift1_start, 0, 5);
            $salonEnd = substr($salonHour->shift1_end, 0, 5);

            $requestedStart = isset($hours['start']) ? substr($hours['start'], 0, 5) : null;
            $requestedEnd = isset($hours['end']) ? substr($hours['end'], 0, 5) : null;

            if (!$requestedStart || !$requestedEnd) {
                $errors[] = "يجب تحديد وقت البدء والنهاية ليوم {$dayName}";
                continue;
            }

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
}
