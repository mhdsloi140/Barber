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
     * جلب أوقات العمل الخاصة بالحلاق
     */
    public function getWorkingHours(User $barber): AuthResult
    {
        try {
            if (!$barber->hasRole('barber')) {
                return AuthResult::error('هذه الخدمة متاحة للحلاقين فقط', null, 403);
            }

            $workingHours = $barber->workingHours()
                ->orderByRaw("FIELD(day_of_week, 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday')")
                ->get();

            if ($workingHours->isEmpty()) {
                $this->createDefaultSchedule($barber);
                $workingHours = $barber->workingHours()
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
                'shift1_start' => $hour->shift1_start,
                'shift1_end' => $hour->shift1_end,
                'shift2_start' => $hour->shift2_start,
                'shift2_end' => $hour->shift2_end,
            ];
        }

        return $salonHours;
    }

    /**
     * التحقق من أن الصالون مفتوح في اليوم المحدد
     */
    private function isSalonOpenOnDay(User $barber, string $dayOfWeek): bool
    {
        $salon = $barber->salons()->first();

        if (!$salon) {
            Log::warning('Barber has no salon', ['barber_id' => $barber->id]);
            return false;
        }

        $salonWorkingHour = $salon->workingHours()
            ->where('day_of_week', $dayOfWeek)
            ->where('is_open', true)
            ->first();

        if (!$salonWorkingHour) {
            Log::info('Salon is closed on this day', [
                'salon_id' => $salon->id,
                'salon_name' => $salon->name,
                'day' => $dayOfWeek
            ]);
            return false;
        }

        return true;
    }

    /**
     * التحقق من أن أوقات الحلاق ضمن أوقات عمل الصالون
     */
    private function isBarberTimeWithinSalonHours(User $barber, string $dayOfWeek, array $barberHours): array
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

        $errors = [];

        // التحقق من الوردية الأولى للحلاق
        if (isset($barberHours['shift1_start']) && isset($barberHours['shift1_end'])) {
            $barberStart = $barberHours['shift1_start'];
            $barberEnd = $barberHours['shift1_end'];

            $isWithinSalonHours = false;

            if ($salonWorkingHour->shift1_start && $salonWorkingHour->shift1_end) {
                if ($barberStart >= $salonWorkingHour->shift1_start && $barberEnd <= $salonWorkingHour->shift1_end) {
                    $isWithinSalonHours = true;
                }
            }

            if (!$isWithinSalonHours && $salonWorkingHour->shift2_start && $salonWorkingHour->shift2_end) {
                if ($barberStart >= $salonWorkingHour->shift2_start && $barberEnd <= $salonWorkingHour->shift2_end) {
                    $isWithinSalonHours = true;
                }
            }

            if (!$isWithinSalonHours) {
                $errors[] = "الوردية الأولى ({$barberStart} - {$barberEnd}) خارج أوقات عمل الصالون";
            }
        }

        // التحقق من الوردية الثانية للحلاق
        if (isset($barberHours['shift2_start']) && isset($barberHours['shift2_end'])) {
            $barberStart = $barberHours['shift2_start'];
            $barberEnd = $barberHours['shift2_end'];

            $isWithinSalonHours = false;

            if ($salonWorkingHour->shift1_start && $salonWorkingHour->shift1_end) {
                if ($barberStart >= $salonWorkingHour->shift1_start && $barberEnd <= $salonWorkingHour->shift1_end) {
                    $isWithinSalonHours = true;
                }
            }

            if (!$isWithinSalonHours && $salonWorkingHour->shift2_start && $salonWorkingHour->shift2_end) {
                if ($barberStart >= $salonWorkingHour->shift2_start && $barberEnd <= $salonWorkingHour->shift2_end) {
                    $isWithinSalonHours = true;
                }
            }

            if (!$isWithinSalonHours) {
                $errors[] = "الوردية الثانية ({$barberStart} - {$barberEnd}) خارج أوقات عمل الصالون";
            }
        }

        if (!empty($errors)) {
            return ['valid' => false, 'message' => implode(', ', $errors)];
        }

        return ['valid' => true, 'message' => ''];
    }

    /**
     * تحديث أوقات العمل الخاصة بالحلاق مع التحقق من الصالون
     * يتم حفظ الأيام التي يكون فيها الصالون مفتوح فقط
     */
    public function updateWorkingHours(User $barber, array $data): AuthResult
    {
        try {
            return DB::transaction(function () use ($barber, $data) {

                if (!$barber->hasRole('barber')) {
                    return AuthResult::error('هذه الخدمة متاحة للحلاقين فقط', null, 403);
                }

                //  جلب أوقات عمل الصالون أولاً
                $salonHours = $this->getSalonWorkingHours($barber);

                if (!$salonHours) {
                    return AuthResult::error('لا يمكن تحديث أوقات العمل لأنك غير مرتبط بأي صالون', null, 404);
                }

                $savedDays = [];
                $skippedDays = [];
                $errors = [];
                $validWorkingHours = [];

                foreach ($data['working_hours'] as $hours) {
                    $day = $hours['day'];
                    $dayName = self::$daysInArabic[$day];

                    // إذا كان اليوم مغلقاً، احفظه كما هو
                    if (!$hours['is_open']) {
                        $validWorkingHours[] = $hours;
                        $savedDays[] = $dayName;
                        continue;
                    }

                    //  التحقق من أن الصالون مفتوح في هذا اليوم
                    if (!isset($salonHours[$day])) {
                        $skippedDays[] = $dayName;
                        Log::info("Day {$dayName} skipped because salon is closed", [
                            'barber_id' => $barber->id,
                            'day' => $day
                        ]);
                        continue;
                    }

                    //  التحقق من أن أوقات الحلاق ضمن أوقات عمل الصالون
                    $validation = $this->isBarberTimeWithinSalonHours($barber, $day, $hours);

                    if (!$validation['valid']) {
                        $errors[] = "{$dayName}: {$validation['message']}";
                        Log::warning("Validation failed for {$dayName}", [
                            'barber_id' => $barber->id,
                            'day' => $day,
                            'error' => $validation['message']
                        ]);
                        continue;
                    }

                    $validWorkingHours[] = $hours;
                    $savedDays[] = $dayName;
                }

                // إذا كانت هناك أخطاء، نرجعها ولا نحفظ شيئاً
                if (!empty($errors)) {
                    return AuthResult::error(
                        'حدثت الأخطاء التالية: ' . implode(', ', $errors),
                        null,
                        400
                    );
                }

                //  حذف الأوقات القديمة
                $barber->workingHours()->delete();

                //  إضافة الأوقات الجديدة (الصالحة فقط)
                foreach ($validWorkingHours as $hours) {
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

                //  إضافة الأيام التي لم يتم إرسالها (ستكون مغلقة)
                $allDays = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
                $submittedDays = array_column($validWorkingHours, 'day');

                foreach ($allDays as $day) {
                    if (!in_array($day, $submittedDays)) {
                        //  إذا كان الصالون مفتوح في هذا اليوم، اجعله مغلقاً للحلاق
                        WorkingHour::create([
                            'workable_type' => User::class,
                            'workable_id' => $barber->id,
                            'day_of_week' => $day,
                            'is_open' => false,
                        ]);
                    }
                }

                Log::info('Working hours updated for barber with salon validation', [
                    'barber_id' => $barber->id,
                    'saved_days' => $savedDays,
                    'skipped_days' => $skippedDays
                ]);

                $workingHours = $barber->workingHours()
                    ->orderByRaw("FIELD(day_of_week, 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday')")
                    ->get();

                //  بناء رسالة النجاح
                $successMessage = 'تم تحديث أوقات العمل بنجاح';
                if (!empty($skippedDays)) {
                    $successMessage .= '، تم تخطي الأيام التالية لأن الصالون مغلق: ' . implode('، ', $skippedDays);
                }

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

                $workingHours = $barber->workingHours()
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
     * تنسيق أوقات العمل للعرض
     */
    private function formatWorkingHours($workingHours): array
    {
        $result = [];

        foreach ($workingHours as $hour) {
            $result[] = [
                'day' => $hour->day_of_week,
                'day_ar' => self::$daysInArabic[$hour->day_of_week],
                'is_open' => (bool) $hour->is_open,
                'morning' => $hour->shift1_start && $hour->shift1_end
                    ? [
                        'start' => $hour->shift1_start,
                        'end' => $hour->shift1_end,
                        'text' => $hour->shift1_start . ' - ' . $hour->shift1_end
                    ]
                    : null,
                'evening' => $hour->shift2_start && $hour->shift2_end
                    ? [
                        'start' => $hour->shift2_start,
                        'end' => $hour->shift2_end,
                        'text' => $hour->shift2_start . ' - ' . $hour->shift2_end
                    ]
                    : null,
                'break' => $hour->break_start && $hour->break_end
                    ? [
                        'start' => $hour->break_start,
                        'end' => $hour->break_end,
                        'text' => $hour->break_start . ' - ' . $hour->break_end
                    ]
                    : null,
                'hours_text' => $this->getWorkingHoursText($hour),
            ];
        }

        return $result;
    }

    /**
     * الحصول على نص أوقات العمل
     */
    private function getWorkingHoursText($hour): string
    {
        if (!$hour->is_open) {
            return 'مغلق';
        }

        $hours = [];

        if ($hour->shift1_start && $hour->shift1_end) {
            $hours[] = $hour->shift1_start . ' - ' . $hour->shift1_end;
        }

        if ($hour->shift2_start && $hour->shift2_end) {
            $hours[] = $hour->shift2_start . ' - ' . $hour->shift2_end;
        }

        return implode(' و ', $hours);
    }

    /**
     * إنشاء جدول عمل افتراضي متوافق مع الصالون
     */
    private function createDefaultSchedule(User $barber): void
    {
        //  جلب الصالون التابع للحلاق
        $salon = $barber->salons()->first();

        if (!$salon) {
            // إذا لم يكن هناك صالون، إنشاء جدول افتراضي بدون أيام عمل
            $days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
            foreach ($days as $day) {
                WorkingHour::create([
                    'workable_type' => User::class,
                    'workable_id' => $barber->id,
                    'day_of_week' => $day,
                    'is_open' => false,
                ]);
            }
            return;
        }

        //  الحصول على أيام عمل الصالون
        $salonWorkingDays = $salon->workingHours()
            ->where('is_open', true)
            ->pluck('day_of_week')
            ->toArray();

        $days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

        foreach ($days as $day) {
            //  إذا كان الصالون مفتوحاً في هذا اليوم، يمكن للحلاق العمل
            if (in_array($day, $salonWorkingDays)) {
                // أيام العمل العادية
                if (in_array($day, ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday'])) {
                    WorkingHour::create([
                        'workable_type' => User::class,
                        'workable_id' => $barber->id,
                        'day_of_week' => $day,
                        'is_open' => true,
                        'shift1_start' => '09:00',
                        'shift1_end' => '22:00',
                    ]);
                }
                // السبت
                elseif ($day === 'saturday') {
                    WorkingHour::create([
                        'workable_type' => User::class,
                        'workable_id' => $barber->id,
                        'day_of_week' => $day,
                        'is_open' => true,
                        'shift1_start' => '10:00',
                        'shift1_end' => '18:00',
                    ]);
                }
                // الجمعة (عطلة)
                else {
                    WorkingHour::create([
                        'workable_type' => User::class,
                        'workable_id' => $barber->id,
                        'day_of_week' => $day,
                        'is_open' => false,
                    ]);
                }
            } else {
                //  إذا كان الصالون مغلقاً، الحلاق أيضاً مغلق
                WorkingHour::create([
                    'workable_type' => User::class,
                    'workable_id' => $barber->id,
                    'day_of_week' => $day,
                    'is_open' => false,
                ]);
            }
        }
    }
}
