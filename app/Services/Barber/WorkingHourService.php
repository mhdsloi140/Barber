<?php
// app/Services/Barber/WorkingHourService.php

namespace App\Services\Barber;

use App\Models\User;
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
            // التحقق من أن المستخدم حلاق
            if (!$barber->hasRole('barber')) {
                return AuthResult::error('هذه الخدمة متاحة للحلاقين فقط', null, 403);
            }

            $workingHours = $barber->workingHours()
                ->orderByRaw("FIELD(day_of_week, 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday')")
                ->get();

            // إذا لم توجد أوقات عمل، أنشئ جدولاً افتراضياً
            if ($workingHours->isEmpty()) {
                $this->createDefaultSchedule($barber);
                $workingHours = $barber->workingHours()
                    ->orderByRaw("FIELD(day_of_week, 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday')")
                    ->get();
            }

            $formatted = $this->formatWorkingHours($workingHours);

            return AuthResult::success(
                'تم جلب أوقات العمل بنجاح',
                $formatted
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

    /**
     * تحديث أوقات العمل الخاصة بالحلاق
     */
    public function updateWorkingHours(User $barber, array $data): AuthResult
    {
        try {
            return DB::transaction(function () use ($barber, $data) {

                // التحقق من أن المستخدم حلاق
                if (!$barber->hasRole('barber')) {
                    return AuthResult::error('هذه الخدمة متاحة للحلاقين فقط', null, 403);
                }

                // حذف الأوقات القديمة
                $barber->workingHours()->delete();

                // إضافة الأوقات الجديدة
                foreach ($data['working_hours'] as $hours) {
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

                Log::info('Working hours updated for barber', [
                    'barber_id' => $barber->id
                ]);

                $workingHours = $barber->workingHours()
                    ->orderByRaw("FIELD(day_of_week, 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday')")
                    ->get();

                return AuthResult::success(
                    'تم تحديث أوقات العمل بنجاح',
                    $this->formatWorkingHours($workingHours)
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

            return AuthResult::error(
                'حدث خطأ أثناء إعادة التعيين',
                config('app.debug') ? $e->getMessage() : null,
                500
            );
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
     * إنشاء جدول عمل افتراضي
     */
    private function createDefaultSchedule(User $barber): void
    {
        $days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

        foreach ($days as $day) {
            // أيام العمل (الأحد إلى الخميس)
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
            // الجمعة (عطلة)
            elseif ($day === 'friday') {
                WorkingHour::create([
                    'workable_type' => User::class,
                    'workable_id' => $barber->id,
                    'day_of_week' => $day,
                    'is_open' => false,
                ]);
            }
            // السبت (دوام مخفض)
            else {
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
