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
     * جلب أوقات عمل الحلاق الحالية
     */
    public function getWorkingHours(User $barber): AuthResult
    {
        try {
            if (!$barber->hasRole('barber')) {
                return AuthResult::error('هذه الخدمة متاحة للحلاقين فقط', null, 403);
            }

            $workingHours = $barber->workingHours()
                ->where('is_open', true)
                ->orderByRaw("FIELD(day_of_week, 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday')")
                ->get();

            return AuthResult::success('تم جلب أوقات العمل بنجاح', $this->formatWorkingHours($workingHours));

        } catch (\Exception $e) {
            Log::error('Get working hours error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب أوقات العمل', null, 500);
        }
    }

    /**
     * حفظ أو تحديث أوقات العمل (يدعم إضافة أيام جديدة وتعديلها وحذفها)
     */
  /**
 * حفظ أو تحديث أوقات العمل (يدعم إضافة أيام جديدة وتعديلها وحذفها)
 */
public function updateWorkingHours(User $barber, array $data): AuthResult
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

            $salonOpenDays = $salon->workingHours()
                ->where('is_open', true)
                ->get()
                ->keyBy('day_of_week');

            if ($salonOpenDays->isEmpty()) {
                return AuthResult::error('لا يمكن تحديث أوقات العمل لأن الصالون لا يحتوي على أيام عمل مفتوحة', null, 400);
            }

            // 🔴 دعم كلا التنسيقين: 'working_hours' أو 'days'
            $workingHours = $data['working_hours'] ?? $data['days'] ?? null;

            if (!$workingHours) {
                return AuthResult::error('البيانات غير صحيحة. يجب إرسال working_hours أو days', null, 400);
            }

            $existingDays = $barber->workingHours()
                ->where('is_open', true)
                ->pluck('day_of_week')
                ->toArray();

            $errors = [];
            $addedDays = [];
            $updatedDays = [];
            $deletedDays = [];

            foreach ($workingHours as $hours) {
                $day = $hours['day'];
                $dayName = $this->daysInArabic[$day];
                $isOpen = (bool) ($hours['is_open'] ?? false);

                if (!$salonOpenDays->has($day)) {
                    $errors[] = "لا يمكن حفظ يوم {$dayName} لأن الصالون مغلق في هذا اليوم";
                    continue;
                }

                $salonDay = $salonOpenDays->get($day);
                $salonStart = substr($salonDay->shift1_start, 0, 5);
                $salonEnd = substr($salonDay->shift1_end, 0, 5);

                if (!$isOpen) {
                    $deleted = WorkingHour::where('workable_type', User::class)
                        ->where('workable_id', $barber->id)
                        ->where('day_of_week', $day)
                        ->delete();
                    if ($deleted) {
                        $deletedDays[] = $dayName;
                    }
                    continue;
                }

                if (empty($hours['start']) || empty($hours['end'])) {
                    $errors[] = "يجب تحديد وقت البدء والنهاية ليوم {$dayName}";
                    continue;
                }

                $start = substr($hours['start'], 0, 5);
                $end = substr($hours['end'], 0, 5);

                if ($start < $salonStart || $end > $salonEnd) {
                    $errors[] = "وقت العمل ليوم {$dayName} ({$start} - {$end}) خارج أوقات عمل الصالون ({$salonStart} - {$salonEnd})";
                    continue;
                }

                if ($start >= $end) {
                    $errors[] = "وقت البدء يجب أن يكون قبل وقت النهاية ليوم {$dayName}";
                    continue;
                }

                $isNewDay = !in_array($day, $existingDays);

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

                if ($isNewDay) {
                    $addedDays[] = $dayName . ' (' . $start . ' - ' . $end . ')';
                } else {
                    $updatedDays[] = $dayName . ' (' . $start . ' - ' . $end . ')';
                }
            }

            if (!empty($errors)) {
                return AuthResult::error('حدثت الأخطاء التالية: ' . implode('، ', $errors), null, 400);
            }

            $updatedWorkingHours = $barber->workingHours()
                ->where('is_open', true)
                ->orderByRaw("FIELD(day_of_week, 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday')")
                ->get();

            $successMessages = [];
            if (!empty($addedDays)) {
                $successMessages[] = 'تم إضافة: ' . implode('، ', $addedDays);
            }
            if (!empty($updatedDays)) {
                $successMessages[] = 'تم تحديث: ' . implode('، ', $updatedDays);
            }
            if (!empty($deletedDays)) {
                $successMessages[] = 'تم إغلاق: ' . implode('، ', $deletedDays);
            }

            $message = 'تم تحديث أوقات العمل بنجاح';
            if (!empty($successMessages)) {
                $message .= ' (' . implode('، ', $successMessages) . ')';
            }

            Log::info('Working hours updated', [
                'barber_id' => $barber->id,
                'added_days' => $addedDays,
                'updated_days' => $updatedDays,
                'deleted_days' => $deletedDays
            ]);

            return AuthResult::success($message, $this->formatWorkingHours($updatedWorkingHours));

        });
    } catch (\Exception $e) {
        Log::error('Update working hours error: ' . $e->getMessage());
        return AuthResult::error('حدث خطأ أثناء تحديث أوقات العمل: ' . $e->getMessage(), null, 500);
    }
}

    /**
     * إضافة عدة أيام جديدة دفعة واحدة
     */
    public function addMultipleWorkingDays(User $barber, array $data): AuthResult
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

                $salonOpenDays = $salon->workingHours()
                    ->where('is_open', true)
                    ->get()
                    ->keyBy('day_of_week');

                if ($salonOpenDays->isEmpty()) {
                    return AuthResult::error('لا يمكن إضافة أيام لأن الصالون لا يحتوي على أيام عمل مفتوحة', null, 400);
                }

                // أيام الحلاق الحالية
                $existingBarberDays = $barber->workingHours()
                    ->where('is_open', true)
                    ->pluck('day_of_week')
                    ->toArray();

                $errors = [];
                $successDays = [];
                $alreadyExistDays = [];

                $daysToAdd = $data['days']; // مصفوفة من الأيام

                foreach ($daysToAdd as $item) {
                    $day = $item['day'];
                    $dayName = $this->daysInArabic[$day];
                    $start = $item['start'];
                    $end = $item['end'];

                    // التحقق من أن اليوم موجود ومفتوح في الصالون
                    if (!$salonOpenDays->has($day)) {
                        $errors[] = "لا يمكن إضافة يوم {$dayName} لأن الصالون مغلق في هذا اليوم";
                        continue;
                    }

                    // التحقق من أن اليوم غير موجود مسبقاً عند الحلاق
                    if (in_array($day, $existingBarberDays)) {
                        $alreadyExistDays[] = $dayName;
                        continue;
                    }

                    $salonDay = $salonOpenDays->get($day);
                    $salonStart = substr($salonDay->shift1_start, 0, 5);
                    $salonEnd = substr($salonDay->shift1_end, 0, 5);

                    $startFormatted = substr($start, 0, 5);
                    $endFormatted = substr($end, 0, 5);

                    // التحقق من أن الوقت ضمن أوقات الصالون
                    if ($startFormatted < $salonStart || $endFormatted > $salonEnd) {
                        $errors[] = "وقت العمل ليوم {$dayName} ({$startFormatted} - {$endFormatted}) خارج أوقات عمل الصالون ({$salonStart} - {$salonEnd})";
                        continue;
                    }

                    if ($startFormatted >= $endFormatted) {
                        $errors[] = "وقت البدء يجب أن يكون قبل وقت النهاية ليوم {$dayName}";
                        continue;
                    }

                    // إضافة اليوم
                    WorkingHour::create([
                        'workable_type' => User::class,
                        'workable_id' => $barber->id,
                        'day_of_week' => $day,
                        'is_open' => true,
                        'shift1_start' => $start,
                        'shift1_end' => $end,
                    ]);

                    $successDays[] = $dayName . ' (' . $startFormatted . ' - ' . $endFormatted . ')';
                }

                // بناء رسالة النجاح
                $message = '';
                if (!empty($successDays)) {
                    $message .= 'تم إضافة الأيام التالية بنجاح: ' . implode('، ', $successDays);
                }
                if (!empty($alreadyExistDays)) {
                    if (!empty($message)) $message .= '، ';
                    $message .= 'الأيام التالية موجودة مسبقاً: ' . implode('، ', $alreadyExistDays);
                }
                if (!empty($errors)) {
                    if (!empty($message)) $message .= '، ';
                    $message .= 'الأخطاء: ' . implode('، ', $errors);
                }

                if (empty($successDays) && empty($alreadyExistDays) && !empty($errors)) {
                    return AuthResult::error($message, null, 400);
                }

                Log::info('Multiple working days added', [
                    'barber_id' => $barber->id,
                    'added_days' => $successDays,
                    'already_exist' => $alreadyExistDays,
                    'errors' => $errors
                ]);

                // جلب الأوقات المحدثة
                $updatedWorkingHours = $barber->workingHours()
                    ->where('is_open', true)
                    ->orderByRaw("FIELD(day_of_week, 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday')")
                    ->get();

                return AuthResult::success($message, $this->formatWorkingHours($updatedWorkingHours));

            });
        } catch (\Exception $e) {
            Log::error('Add multiple working days error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء إضافة الأيام: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * إضافة يوم واحد (اختصار)
     */
    public function addSingleWorkingDay(User $barber, array $data): AuthResult
    {
        return $this->addMultipleWorkingDays($barber, ['days' => [$data]]);
    }

    /**
     * حذف يوم عمل
     */
    public function deleteWorkingDay(User $barber, string $day): AuthResult
    {
        try {
            $dayName = $this->daysInArabic[$day];

            $deleted = WorkingHour::where('workable_type', User::class)
                ->where('workable_id', $barber->id)
                ->where('day_of_week', $day)
                ->delete();

            if (!$deleted) {
                return AuthResult::error("يوم {$dayName} غير موجود في جدول أوقات عملك", null, 404);
            }

            Log::info('Working day deleted', [
                'barber_id' => $barber->id,
                'day' => $dayName
            ]);

            return AuthResult::success("تم حذف يوم {$dayName} بنجاح", null);

        } catch (\Exception $e) {
            Log::error('Delete working day error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء حذف اليوم: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * جلب أيام عمل الصالون المفتوحة
     */
    public function getSalonOpenDaysForBarber(User $barber): AuthResult
    {
        try {
            if (!$barber->hasRole('barber')) {
                return AuthResult::error('هذه الخدمة متاحة للحلاقين فقط', null, 403);
            }

            $salon = $barber->salons()->first();
            if (!$salon) {
                return AuthResult::error('لا يوجد صالون تابع لك', null, 404);
            }

            $salonOpenDays = $salon->workingHours()
                ->where('is_open', true)
                ->orderByRaw("FIELD(day_of_week, 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday')")
                ->get();

            $barberCurrentDays = $barber->workingHours()
                ->where('is_open', true)
                ->pluck('day_of_week')
                ->toArray();

            $formattedDays = [];
            foreach ($salonOpenDays as $day) {
                $formattedDays[] = [
                    'day' => $day->day_of_week,
                    'day_ar' => $this->daysInArabic[$day->day_of_week],
                    'salon_start' => substr($day->shift1_start, 0, 5),
                    'salon_end' => substr($day->shift1_end, 0, 5),
                    'is_already_added' => in_array($day->day_of_week, $barberCurrentDays),
                ];
            }

            return AuthResult::success('تم جلب أيام عمل الصالون المفتوحة بنجاح', [
                'salon' => [
                    'id' => $salon->id,
                    'name' => $salon->name,
                ],
                'available_days' => $formattedDays,
                'total_days' => count($formattedDays),
                'my_current_days' => $barberCurrentDays,
            ]);

        } catch (\Exception $e) {
            Log::error('Get salon open days error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب أيام العمل', null, 500);
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
                'day_ar' => $this->daysInArabic[$hour->day_of_week],
                'start' => $hour->shift1_start,
                'end' => $hour->shift1_end,
                'hours_text' => $hour->shift1_start . ' - ' . $hour->shift1_end,
            ];
        }
        return $result;
    }
}
