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

            //  معلومات الصالون الأساسية
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

            //  الحلاقين مع خدماتهم
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
                            'name_ar' => $service->name_ar,
                            'description' => $service->description,
                            'price' => $service->price,
                            'duration_minutes' => $service->duration_minutes,
                        ];
                    });

                // جلب أوقات عمل الحلاق
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

            //  أوقات عمل الصالون
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
     * تنسيق أوقات العمل للعرض
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

        $workingHours = $workable->workingHours()
            ->orderByRaw("FIELD(day_of_week, 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday')")
            ->get();

        $result = [];
        foreach ($workingHours as $hour) {
            $result[] = [
                'day' => $hour->day_of_week,
                'day_ar' => $daysInArabic[$hour->day_of_week],
                'is_open' => (bool) $hour->is_open,
                'hours' => $this->getHoursText($hour),
                'morning' => $hour->shift1_start && $hour->shift1_end
                    ? $hour->shift1_start . ' - ' . $hour->shift1_end
                    : null,
                'evening' => $hour->shift2_start && $hour->shift2_end
                    ? $hour->shift2_start . ' - ' . $hour->shift2_end
                    : null,
            ];
        }
        return $result;
    }

    /**
     * الحصول على نص ساعات العمل
     */
    private function getHoursText($hour): string
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
     * التحقق من توفر الحلاق اليوم
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

        if ($workingHour->shift1_start && $workingHour->shift1_end) {
            if ($currentTime >= $workingHour->shift1_start && $currentTime <= $workingHour->shift1_end) {
                return true;
            }
        }

        if ($workingHour->shift2_start && $workingHour->shift2_end) {
            if ($currentTime >= $workingHour->shift2_start && $currentTime <= $workingHour->shift2_end) {
                return true;
            }
        }

        return false;
    }
}
