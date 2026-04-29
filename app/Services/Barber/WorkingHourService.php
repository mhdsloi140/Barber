<?php
// app/Services/Barber/WorkingHourService.php

namespace App\Services\Barber;

use App\Models\User;
use App\Models\Salon;
use App\Models\WorkingHour;
use App\Services\AuthResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WorkingHourService
{
    // أيام الأسبوع بالعربية
    public static $daysInArabic = [
        'sunday' => 'الأحد',
        'monday' => 'الإثنين',
        'tuesday' => 'الثلاثاء',
        'wednesday' => 'الأربعاء',
        'thursday' => 'الخميس',
        'friday' => 'الجمعة',
        'saturday' => 'السبت',
    ];

    /**
     * ✅ جلب أيام العمل فقط (الأيام المفتوحة)
     */
    public function getWorkingHours(User $barber): AuthResult
    {
        try {
            if (!$barber->hasRole('barber')) {
                return AuthResult::error('هذه الخدمة متاحة للحلاقين فقط', null, 403);
            }

            // ✅ جلب الأيام المفتوحة فقط
            $workingHours = $barber->workingHours()
                ->where('is_open', true)
                ->orderByRaw("FIELD(day_of_week, 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday')")
                ->get();

            // إذا لم توجد أيام عمل، أنشئ الجدول الافتراضي
            if ($workingHours->isEmpty()) {
                $this->createDefaultSchedule($barber);
                $workingHours = $barber->workingHours()
                    ->where('is_open', true)
                    ->orderByRaw("FIELD(day_of_week, 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday')")
                    ->get();
            }

            $formatted = $this->formatWorkingHours($workingHours);

            return AuthResult::success('تم جلب أوقات العمل بنجاح', $formatted);

        } catch (\Exception $e) {
            Log::error('Get working hours error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب أوقات العمل', config('app.debug') ? $e->getMessage() : null, 500);
        }
    }

    /**
     * جلب أوقات عمل الصالون التابع للحلاق
     */
    private function getSalonWorkingHours(User $barber): ?array
    {
        $salon = $barber->salons()->first();

        if (!$salon) {
            return null;
        }

        $salonHours = [];
        $salonWorkingHours = $salon->workingHours()
            ->where('is_open', true)
            ->get();

        foreach ($salonWorkingHours as $hour) {
            $salonHours[$hour->day_of_week] = [
                'start' => $hour->shift1_start,
                'end' => $hour->shift1_end,
            ];
        }

        return $salonHours;
    }

    /**
     * التحقق من أن الوقت ضمن أوقات عمل الصالون
     */
    private function isTimeWithinSalonHours(User $barber, string $dayOfWeek, string $start, string $end): array
    {
        $salon = $barber->salons()->first();

        if (!$salon) {
            return ['valid' => false, 'message' => 'لا يوجد صالون تابع لك'];
        }

        $salonWorkingHour = $salon->workingHours()
            ->where('day_of_week', $dayOfWeek)
            ->where('is_open', true)
            ->first();

        if (!$salonWorkingHour) {
            return ['valid' => false, 'message' => 'الصالون مغلق في هذا اليوم'];
        }

        $salonStart = $salonWorkingHour->shift1_start;
        $salonEnd = $salonWorkingHour->shift1_end;

        if ($start >= $salonStart && $end <= $salonEnd) {
            return ['valid' => true, 'message' => ''];
        }

        return ['valid' => false, 'message' => "الوقت ({$start} - {$end}) خارج أوقات عمل الصالون ({$salonStart} - {$salonEnd})"];
    }

    /**
     * تحديث أوقات العمل
     */
    public function updateWorkingHours(User $barber, array $data): AuthResult
    {
        try {
            return DB::transaction(function () use ($barber, $data) {

                if (!$barber->hasRole('barber')) {
                    return AuthResult::error('هذه الخدمة متاحة للحلاقين فقط', null, 403);
                }

                // جلب أوقات عمل الصالون
                $salonHours = $this->getSalonWorkingHours($barber);

                if (!$salonHours) {
                    return AuthResult::error('لا يمكن تحديث أوقات العمل لأنك غير مرتبط بأي صالون', null, 404);
                }

                $savedDays = [];
                $skippedDays = [];
                $errors = [];
                $newDaysAdded = [];

                foreach ($data['working_hours'] as $hours) {
                    $day = $hours['day'];
                    $dayName = self::$daysInArabic[$day];

                    // التحقق من وجود اليوم في الصالون
                    if (!isset($salonHours[$day])) {
                        $skippedDays[] = $dayName;
                        Log::info("Day {$dayName} skipped because salon is closed", [
                            'barber_id' => $barber->id,
                            'day' => $day
                        ]);
                        continue;
                    }

                    // إذا كان اليوم مغلقاً في التحديث
                    if (!$hours['is_open']) {
                        // حذف اليوم إذا كان موجوداً (لا نريد تخزين الأيام المغلقة)
                        WorkingHour::where('workable_type', User::class)
                            ->where('workable_id', $barber->id)
                            ->where('day_of_week', $day)
                            ->delete();
                        $savedDays[] = $dayName;
                        continue;
                    }

                    // التحقق من صحة الأوقات
                    if (!isset($hours['start']) || !isset($hours['end'])) {
                        $errors[] = "{$dayName}: يجب إدخال وقت البدء والنهاية";
                        continue;
                    }

                    // التحقق من أن الوقت ضمن ساعات عمل الصالون
                    $timeCheck = $this->isTimeWithinSalonHours($barber, $day, $hours['start'], $hours['end']);
                    if (!$timeCheck['valid']) {
                        $errors[] = "{$dayName}: {$timeCheck['message']}";
                        continue;
                    }

                    // ✅ حفظ اليوم (مفتوح)
                    $existing = WorkingHour::where('workable_type', User::class)
                        ->where('workable_id', $barber->id)
                        ->where('day_of_week', $day)
                        ->first();

                    if (!$existing) {
                        $newDaysAdded[] = $dayName;
                    }

                    WorkingHour::updateOrCreate(
                        [
                            'workable_type' => User::class,
                            'workable_id' => $barber->id,
                            'day_of_week' => $day,
                        ],
                        [
                            'is_open' => true,
                            'shift1_start' => $hours['start'],
                            'shift1_end' => $hours['end'],
                        ]
                    );
                    $savedDays[] = $dayName;
                }

                // إذا كانت هناك أخطاء
                if (!empty($errors)) {
                    return AuthResult::error(
                        'حدثت الأخطاء التالية: ' . implode(', ', $errors),
                        null,
                        400
                    );
                }

                Log::info('Working hours updated', [
                    'barber_id' => $barber->id,
                    'saved_days' => $savedDays,
                    'skipped_days' => $skippedDays,
                    'new_days_added' => $newDaysAdded
                ]);

                // بناء رسالة النجاح
                $successMessage = 'تم تحديث أوقات العمل بنجاح';
                if (!empty($skippedDays)) {
                    $successMessage .= '، تم تخطي الأيام التالية لأن الصالون مغلق: ' . implode('، ', $skippedDays);
                }
                if (!empty($newDaysAdded)) {
                    $successMessage .= '، تم إضافة أيام جديدة: ' . implode('، ', $newDaysAdded);
                }

                // ✅ إرجاع الأيام المفتوحة فقط
                $workingHours = $barber->workingHours()
                    ->where('is_open', true)
                    ->orderByRaw("FIELD(day_of_week, 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday')")
                    ->get();

                return AuthResult::success(
                    $successMessage,
                    $this->formatWorkingHours($workingHours)
                );

            });
        } catch (\Exception $e) {
            Log::error('Update working hours error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء تحديث أوقات العمل', config('app.debug') ? $e->getMessage() : null, 500);
        }
    }

    /**
     * إعادة تعيين أوقات العمل إلى الافتراضية
     */
    public function resetWorkingHours(User $barber): AuthResult
    {
        try {
            return DB::transaction(function () use ($barber) {

                if (!$barber->hasRole('barber')) {
                    return AuthResult::error('هذه الخدمة متاحة للحلاقين فقط', null, 403);
                }

                $barber->workingHours()->delete();
                $this->createDefaultSchedule($barber);

                // ✅ إرجاع الأيام المفتوحة فقط
                $workingHours = $barber->workingHours()
                    ->where('is_open', true)
                    ->orderByRaw("FIELD(day_of_week, 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday')")
                    ->get();

                return AuthResult::success(
                    'تم إعادة تعيين أوقات العمل بنجاح',
                    $this->formatWorkingHours($workingHours)
                );

            });
        } catch (\Exception $e) {
            Log::error('Reset working hours error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء إعادة التعيين', config('app.debug') ? $e->getMessage() : null, 500);
        }
    }

    /**
     * تنسيق أوقات العمل للعرض (الأيام المفتوحة فقط)
     */
    private function formatWorkingHours($workingHours): array
    {
        $result = [];

        foreach ($workingHours as $hour) {
            $result[] = [
                'day' => $hour->day_of_week,
                'day_ar' => self::$daysInArabic[$hour->day_of_week],
                'start' => $hour->shift1_start,
                'end' => $hour->shift1_end,
                'hours_text' => $hour->shift1_start . ' - ' . $hour->shift1_end,
            ];
        }

        return $result;
    }

    /**
     * إنشاء جدول عمل افتراضي (الأيام المفتوحة فقط)
     */
    private function createDefaultSchedule(User $barber): void
    {
        $salon = $barber->salons()->first();

        if (!$salon) {
            return;
        }

        $salonWorkingDays = $salon->workingHours()
            ->where('is_open', true)
            ->get();

        foreach ($salonWorkingDays as $salonHour) {
            $day = $salonHour->day_of_week;

            // تحديد الوقت الافتراضي حسب اليوم
            if (in_array($day, ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday'])) {
                $start = '09:00';
                $end = '22:00';
            } elseif ($day === 'saturday') {
                $start = '10:00';
                $end = '18:00';
            } else {
                continue; // الجمعة مغلق
            }

            WorkingHour::create([
                'workable_type' => User::class,
                'workable_id' => $barber->id,
                'day_of_week' => $day,
                'is_open' => true,
                'shift1_start' => $start,
                'shift1_end' => $end,
            ]);
        }
    }
      public function getSalonWorkingDays(User $barber): AuthResult
    {
        try {
            if (!$barber->hasRole('barber')) {
                return AuthResult::error('هذه الخدمة متاحة للحلاقين فقط', null, 403);
            }

            // جلب الصالون التابع للحلاق
            $salon = $barber->salons()->first();

            if (!$salon) {
                return AuthResult::error('لا يوجد صالون تابع لك', null, 404);
            }

            // جلب أيام عمل الحلاق (المفتوحة)
            $barberWorkingDays = $barber->workingHours()
                ->where('is_open', true)
                ->pluck('day_of_week')
                ->toArray();

            // جلب أيام عمل الصالون (المفتوحة)
            $salonWorkingHours = $salon->workingHours()
                ->where('is_open', true)
                ->orderByRaw("FIELD(day_of_week, 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday')")
                ->get();

            // أيام عمل الصالون التي يعمل بها الحلاق
            $workingDays = [];
            foreach ($salonWorkingHours as $hour) {
                // إذا كان الحلاق يعمل في هذا اليوم
                if (in_array($hour->day_of_week, $barberWorkingDays)) {
                    $workingDays[] = [
                        'day' => $hour->day_of_week,
                        'day_ar' => self::$daysInArabic[$hour->day_of_week],
                        'start' => $hour->shift1_start,
                        'end' => $hour->shift1_end,
                        'hours_text' => $hour->shift1_start . ' - ' . $hour->shift1_end,
                    ];
                }
            }

            return AuthResult::success('تم جلب أيام عمل الصالون التي تعمل بها بنجاح', [
                'salon' => [
                    'id' => $salon->id,
                    'name' => $salon->name,
                ],
                'working_days' => $workingDays,
                'total_days' => count($workingDays),
            ]);

        } catch (\Exception $e) {
            Log::error('Get salon working days error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب أيام العمل', $e->getMessage(), 500);
        }
    }
}
