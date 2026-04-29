<?php
// app/Services/Customer/SalonDetailsService.php

namespace App\Services\Customer;

use App\Models\Salon;
use App\Services\AuthResult;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SalonDetailsService
{
    /**
     * عرض جميع بيانات الصالون (الحلاقين، الخدمات، أوقات العمل)
     */
    public function getSalonDetails(int $salonId): AuthResult
    {
        try {
            $salon = Salon::where('is_active', true)
                ->with(['barbers' => function($q) {
                    $q->where('users.is_active', true);
                }])
                ->find($salonId);

            if (!$salon) {
                return AuthResult::error('الصالون غير موجود', null, 404);
            }

            // معلومات الصالون الأساسية
            $salonData = [
                'id' => $salon->id,
                'name' => $salon->name,
                'address' => $salon->address,
                'phone' => $salon->phone,
                'latitude' => $salon->latitude,
                'longitude' => $salon->longitude,
                'images' => $salon->getImagesUrlsAttribute(),
                'rating' => 4.9,
                'reviews_count' => 120,
            ];

            // الحلاقين مع خدماتهم
            $barbers = [];
            foreach ($salon->barbers as $barber) {
                // جلب خدمات الحلاق
                $services = $barber->barberServices()
                    ->where('is_active', true)
                    ->get()
                    ->map(function($service) {
                        return [
                            'id' => $service->id,
                            'name' => $service->name,
                            'description' => $service->description,
                            'price' => $service->price,
                            'duration_minutes' => $service->duration_minutes,
                        ];
                    });

                // جلب أوقات عمل الحلاق (فترة واحدة)
                $workingHours = $this->getWorkingHoursFormatted($barber);

                $barbers[] = [
                    'id' => $barber->id,
                    'name' => $barber->name,
                    'phone' => $barber->phone,
                    'avatar' => $barber->getAvatarUrlAttribute(),
                    'rating' => 4.8,
                    'services' => $services,
                    'working_hours' => $workingHours,
                    'is_available_today' => $this->isAvailableToday($barber),
                ];
            }

            // أوقات عمل الصالون (فترة واحدة)
            $salonWorkingHours = $this->getWorkingHoursFormatted($salon);

            return AuthResult::success('تم جلب بيانات الصالون بنجاح', [
                'salon' => $salonData,
                'barbers' => $barbers,
                'salon_working_hours' => $salonWorkingHours,
                'total_barbers' => count($barbers),
            ]);

        } catch (\Exception $e) {
            Log::error('Get salon details error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب بيانات الصالون', $e->getMessage(), 500);
        }
    }

    /**
     *  تنسيق أوقات العمل (فترة واحدة فقط من - إلى)
     */
    private function getWorkingHoursFormatted($workable): array
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

        //  جلب الأيام المفتوحة فقط وترتيبها
        $workingHours = $workable->workingHours()
            ->where('is_open', true)
            ->orderByRaw("FIELD(day_of_week, 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday')")
            ->get();

        $result = [];
        foreach ($workingHours as $hour) {
            $result[] = [
                'day' => $hour->day_of_week,
                'day_ar' => $daysInArabic[$hour->day_of_week],
                'is_open' => true,
                //  فترة واحدة فقط (من - إلى)
                'start' => $hour->shift1_start,
                'end' => $hour->shift1_end,
                'hours_text' => $this->getHoursText($hour),
            ];
        }

        return $result;
    }

    /**
     *  الحصول على نص ساعات العمل (فترة واحدة)
     */
    private function getHoursText($hour): string
    {
        if (!$hour->is_open) {
            return 'مغلق';
        }

        //  فترة واحدة فقط (تجاهل shift2)
        if ($hour->shift1_start && $hour->shift1_end) {
            return $hour->shift1_start . ' - ' . $hour->shift1_end;
        }

        return 'مغلق';
    }

    /**
     *  التحقق من توفر الحلاق اليوم (بناءً على فترة واحدة)
     */
    private function isAvailableToday($barber): bool
    {
        $today = strtolower(Carbon::now()->format('l'));
        $currentTime = Carbon::now()->format('H:i');

        $workingHour = $barber->workingHours()
            ->where('day_of_week', $today)
            ->where('is_open', true)
            ->first();

        if (!$workingHour) {
            return false;
        }

        //  التحقق من الفترة الواحدة فقط
        if ($workingHour->shift1_start && $workingHour->shift1_end) {
            if ($currentTime >= $workingHour->shift1_start && $currentTime <= $workingHour->shift1_end) {
                return true;
            }
        }

        return false;
    }
}
