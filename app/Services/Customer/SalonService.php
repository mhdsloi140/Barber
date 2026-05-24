<?php
// app/Services/Customer/SalonService.php

namespace App\Services\Customer;

use App\Models\Salon;
use App\Models\Rating;
use App\Models\WorkingHour;
use App\Models\Appointment;
use App\Models\BarberService;
use App\Services\AuthResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SalonService
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

    // أشهر السنة بالعربية
    private $monthsInArabic = [
        1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
        5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
        9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
    ];

    /**
     * معالجة التاريخ المدخل
     */
    private function parseDate(?string $date): string
    {
        if (empty($date)) {
            return Carbon::now()->format('Y-m-d');
        }

        if (strtolower($date) === 'tomorrow' || $date === 'غداً' || $date === 'غدا') {
            return Carbon::tomorrow()->format('Y-m-d');
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }

        return Carbon::now()->format('Y-m-d');
    }

    /**
     * تنسيق التاريخ بالعربية
     */
    private function formatDateInArabic(string $date): string
    {
        $carbonDate = Carbon::parse($date);
        $dayOfWeek = $this->daysInArabic[strtolower($carbonDate->format('l'))];
        $day = $carbonDate->format('d');
        $month = $this->monthsInArabic[(int)$carbonDate->format('m')];
        $year = $carbonDate->format('Y');
        return "{$dayOfWeek}، {$day} {$month} {$year}";
    }

    /**
     * عرض جميع الصالونات للزبون مع الصور والتقييمات
     */
    public function getSalons(array $filters): AuthResult
    {
        try {
            $query = Salon::where('salons.is_active', true)
                ->with(['barbers' => function($q) {
                    $q->where('users.is_active', true);
                }]);

            if (!empty($filters['search'])) {
                $query->where(function($q) use ($filters) {
                    $q->where('salons.name', 'like', '%' . $filters['search'] . '%')
                      ->orWhere('salons.address', 'like', '%' . $filters['search'] . '%');
                });
            }

            $latitude = $filters['latitude'] ?? null;
            $longitude = $filters['longitude'] ?? null;

            if ($latitude && $longitude) {
                $query->selectRaw("salons.*, (
                    6371 * acos(
                        cos(radians(?)) * cos(radians(salons.latitude)) *
                        cos(radians(salons.longitude) - radians(?)) +
                        sin(radians(?)) * sin(radians(salons.latitude))
                    )
                ) AS distance", [$latitude, $longitude, $latitude])
                ->orderBy('distance', 'asc');
            } else {
                $query->orderBy('salons.name', 'asc');
            }

            $perPage = $filters['per_page'] ?? 10;
            $salons = $query->paginate($perPage);

            $salons->getCollection()->transform(function ($salon) use ($latitude, $longitude) {
                return $this->formatSalonDataWithImagesAndRatings($salon, $latitude, $longitude);
            });

            return AuthResult::success('تم جلب الصالونات بنجاح', [
                'salons' => $salons->items(),
                'pagination' => [
                    'current_page' => $salons->currentPage(),
                    'last_page' => $salons->lastPage(),
                    'total' => $salons->total(),
                    'per_page' => $salons->perPage(),
                    'has_more_pages' => $salons->hasMorePages(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get salons error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب الصالونات', config('app.debug') ? $e->getMessage() : null, 500);
        }
    }

    /**
     * عرض صالون محدد
     * - إذا تم إرسال barber_id يعيد أوقات الفراغ لهذا الحلاق فقط
     * - إذا لم يتم إرسال barber_id لا يعيد أي أوقات فراغ
     * - إذا لم يتم إرسال تاريخ، يستخدم تاريخ اليوم تلقائياً
     */
    public function getSalon($id, ?float $latitude = null, ?float $longitude = null, ?string $date = null, ?int $barberId = null, ?int $serviceId = null): AuthResult
    {
        try {
            $salonId = (int) $id;

            if ($salonId <= 0) {
                return AuthResult::error('معرف الصالون غير صالح', null, 400);
            }

            $salon = Salon::where('salons.is_active', true)
                ->with([
                    'barbers' => function($q) {
                        $q->where('users.is_active', true);
                    },
                    'barbers.barberServices' => function($q) {
                        $q->where('is_active', true);
                    },
                    'workingHours'
                ])
                ->find($salonId);

            if (!$salon) {
                return AuthResult::error('الصالون غير موجود', null, 404);
            }

            $data = $this->formatSalonDataWithImagesAndRatings($salon, $latitude, $longitude);

            // إضافة تفاصيل إضافية للصالون
            $data['barbers'] = $this->getBarbersDataWithRatings($salon);
            $data['working_hours'] = $this->getWorkingHoursFormatted($salon);
            $data['services'] = $this->getSalonServices($salon);

            // 🔴 فقط إذا تم إرسال barber_id، نعيد أوقات الفراغ
            if ($barberId) {
                // إذا لم يتم إرسال تاريخ، استخدم تاريخ اليوم
                $parsedDate = $this->parseDate($date);
                $barber = $salon->barbers->where('id', $barberId)->first();

                if ($barber) {
                    $freeSlots = $this->getBarberFreeSlots($barber, $parsedDate, $serviceId);
                    $data['barber_free_slots'] = $freeSlots;
                    $data['selected_barber'] = [
                        'id' => $barber->id,
                        'name' => $barber->name,
                        'avatar' => $barber->getAvatarUrlAttribute(),
                    ];
                    $data['selected_date'] = $parsedDate;
                    $data['selected_date_formatted'] = $this->formatDateInArabic($parsedDate);
                    // $data['selected_service_id'] = $serviceId;
                } else {
                    $data['barber_free_slots'] = [
                        'is_working_day' => false,
                        'message' => 'الحلاق غير موجود في هذا الصالون',
                        'slots' => [],
                        'slots_count' => 0
                    ];
                }
            }

            return AuthResult::success('تم جلب بيانات الصالون بنجاح', $data);

        } catch (\Exception $e) {
            Log::error('Get salon error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب بيانات الصالون', config('app.debug') ? $e->getMessage() : null, 500);
        }
    }

 /**
 * جلب أوقات الفراغ لحلاق معين في يوم محدد
 */
private function getBarberFreeSlots($barber, string $date, ?int $serviceId = null): array
{
    try {
        $dateObj = Carbon::parse($date);
        $dayOfWeek = strtolower($dateObj->format('l'));
        $dayName = $this->daysInArabic[$dayOfWeek];

        // جلب أوقات عمل الحلاق في هذا اليوم
        $workingHour = $barber->workingHours()
            ->where('day_of_week', $dayOfWeek)
            ->where('is_open', true)
            ->first();

        if (!$workingHour) {
            return [
                'is_working_day' => false,
                'message' => "الحلاق لا يعمل يوم {$dayName}",
                'slots' => [],
                'slots_count' => 0
            ];
        }

        // جلب مدة الخدمة
        $serviceDuration = null;
        $serviceName = null;
        $servicePrice = null;

        if ($serviceId) {
            $service = $barber->barberServices()->where('id', $serviceId)->first();
            if ($service) {
                $serviceDuration = $service->duration_minutes;
                $serviceName = $service->name;
                $servicePrice = $service->price;
            }
        }

        if (!$serviceDuration) {
            $firstService = $barber->barberServices()->where('is_active', true)->first();
            if ($firstService) {
                $serviceDuration = $firstService->duration_minutes;
                $serviceName = $firstService->name;
                $servicePrice = $firstService->price;
            }
        }

        if (!$serviceDuration) {
            return [
                'is_working_day' => true,
                'message' => 'لا توجد خدمات متاحة لهذا الحلاق',
                'slots' => [],
                'slots_count' => 0
            ];
        }

        // جلب الحجوزات في هذا اليوم
        $bookedAppointments = Appointment::where('barber_id', $barber->id)
            ->whereDate('appointment_date', $date)
            ->whereIn('status', ['pending', 'confirmed','cancelled'])
            ->get();

        // توليد الفترات المتاحة
        $allSlots = $this->generateTimeSlots($workingHour->shift1_start, $workingHour->shift1_end, $serviceDuration);
        $availableSlots = $this->filterBookedSlots($allSlots, $bookedAppointments);

        return [
            'is_working_day' => true,
            'barber_name' => $barber->name,
            'barber_avatar' => $barber->getAvatarUrlAttribute(),
            'service' => [
                'id' => $serviceId,
                'name' => $serviceName,
                'price' => $servicePrice,
                'duration_minutes' => $serviceDuration,
            ],
            'working_hours' => [
                'start' => $workingHour->shift1_start,
                'end' => $workingHour->shift1_end,
            ],
            'available' => $availableSlots,
            'available_count' => count($availableSlots),
        ];

    } catch (\Exception $e) {
        Log::error('Get barber free slots error: ' . $e->getMessage());
        return [
            'is_working_day' => false,
            'message' => 'حدث خطأ أثناء جلب أوقات الفراغ',
            'slots' => [],
            'slots_count' => 0
        ];
    }
}

/**
 * توليد فترات زمنية بين وقت البدء ووقت النهاية
 */
// private function generateTimeSlots(string $startTime, string $endTime, int $slotDuration): array
// {
//     $slots = [];
//     $current = Carbon::parse($startTime);
//     $end = Carbon::parse($endTime);

//     while ($current->lt($end)) {
//         $slotEnd = (clone $current)->addMinutes($slotDuration);

//         if ($slotEnd->lte($end)) {
//             $slots[] = [
//                 'start' => $current->format('H:i'),
//                 'end' => $slotEnd->format('H:i'),
//                 'start_minutes' => ($current->hour * 60) + $current->minute,
//                 'end_minutes' => ($slotEnd->hour * 60) + $slotEnd->minute,
//             ];
//         }

//         $current->addMinutes($slotDuration);
//     }

//     return $slots;
// }

/**
 * إزالة الفترات المحجوزة من قائمة الفترات المتاحة (محسنة)
 */
private function filterBookedSlots(array $allSlots, $bookedAppointments): array
{
    $availableSlots = [];

    foreach ($allSlots as $slot) {
        $isBooked = false;
        $slotStart = $slot['start_minutes'];
        $slotEnd = $slot['end_minutes'];

        foreach ($bookedAppointments as $appointment) {
            // تحويل وقت الحجز إلى دقائق
            $appointmentStart = Carbon::parse($appointment->appointment_time);
            $appointmentEnd = Carbon::parse($appointment->end_time);

            $appointmentStartMinutes = ($appointmentStart->hour * 60) + $appointmentStart->minute;
            $appointmentEndMinutes = ($appointmentEnd->hour * 60) + $appointmentEnd->minute;

            // التحقق من التداخل: إذا كان الفترة تتداخل مع الحجز
            if (
                ($slotStart >= $appointmentStartMinutes && $slotStart < $appointmentEndMinutes) ||
                ($slotEnd > $appointmentStartMinutes && $slotEnd <= $appointmentEndMinutes) ||
                ($slotStart <= $appointmentStartMinutes && $slotEnd >= $appointmentEndMinutes)
            ) {
                $isBooked = true;
                break;
            }
        }

        if (!$isBooked) {
            $availableSlots[] = [
                'start' => $slot['start'],
                'end' => $slot['end'],
            ];
        }
    }

    return $availableSlots;
}

  /**
 * توليد فترات زمنية بين وقت البدء ووقت النهاية
 */
private function generateTimeSlots(string $startTime, string $endTime, int $slotDuration): array
{
    $slots = [];
    $current = Carbon::parse($startTime);
    $end = Carbon::parse($endTime);

    while ($current->lt($end)) {
        $slotEnd = (clone $current)->addMinutes($slotDuration);

        if ($slotEnd->lte($end)) {
            $slots[] = [
                'start' => $current->format('H:i'),
                'end' => $slotEnd->format('H:i'),
                'start_minutes' => $current->hour * 60 + $current->minute,
                'end_minutes' => $slotEnd->hour * 60 + $slotEnd->minute,
            ];
        }

        $current->addMinutes($slotDuration);
    }

    return $slots;
}

    /**
     * إزالة الفترات المحجوزة من قائمة الفترات المتاحة (محسنة)
     */
    // private function filterBookedSlots(array $allSlots, $bookedAppointments): array
    // {
    //     $availableSlots = [];

    //     foreach ($allSlots as $slot) {
    //         $isBooked = false;
    //         $slotStart = $slot['start_minutes'];
    //         $slotEnd = $slot['end_minutes'];

    //         foreach ($bookedAppointments as $appointment) {
    //             // تحويل وقت الحجز إلى دقائق
    //             $appointmentStart = Carbon::parse($appointment->appointment_time);
    //             $appointmentEnd = Carbon::parse($appointment->end_time);

    //             $appointmentStartMinutes = $appointmentStart->hours * 60 + $appointmentStart->minutes;
    //             $appointmentEndMinutes = $appointmentEnd->hours * 60 + $appointmentEnd->minutes;

    //             // التحقق من التداخل: إذا كان الفترة تتداخل مع الحجز
    //             if (
    //                 ($slotStart >= $appointmentStartMinutes && $slotStart < $appointmentEndMinutes) ||
    //                 ($slotEnd > $appointmentStartMinutes && $slotEnd <= $appointmentEndMinutes) ||
    //                 ($slotStart <= $appointmentStartMinutes && $slotEnd >= $appointmentEndMinutes)
    //             ) {
    //                 $isBooked = true;
    //                 break;
    //             }
    //         }

    //         if (!$isBooked) {
    //             $availableSlots[] = [
    //                 'start' => $slot['start'],
    //                 'end' => $slot['end'],
    //             ];
    //         }
    //     }

    //     return $availableSlots;
    // }

    /**
     * تنسيق بيانات الصالون مع جميع الصور والتقييمات
     */
    private function formatSalonDataWithImagesAndRatings(Salon $salon, ?float $latitude = null, ?float $longitude = null): array
    {
        $images = $salon->getMedia('salon_images')->map(function ($image) {
            return [
                'id' => $image->id,
                'original' => $image->getUrl(),
                'medium' => $image->getUrl('medium'),
                'thumb' => $image->getUrl('thumb'),
                'large' => $image->getUrl('large'),
                'file_name' => $image->file_name,
                'size' => $image->size,
                'mime_type' => $image->mime_type,
                'order' => $image->order_column,
                'created_at' => $image->created_at,
            ];
        });

        $salonRatings = $this->getSalonRatings($salon->id);

        $distance = null;
        if ($latitude && $longitude && $salon->latitude && $salon->longitude) {
            $distance = $this->calculateDistance($salon, $latitude, $longitude);
        }

        return [
            'id' => $salon->id,
            'name' => $salon->name,
            'address' => $salon->address,
            'phone' => $salon->phone,
            'latitude' => $salon->latitude,
            'longitude' => $salon->longitude,
            'distance' => $distance,
            'min_price' => $this->getMinPrice($salon),
            'is_active' => $salon->is_active,
            'created_at' => $salon->created_at,
            'updated_at' => $salon->updated_at,
            'images' => $images,
            'images_count' => $images->count(),
            'rating' => $salonRatings['rating'],
        ];
    }

    /**
     * جلب تقييمات الصالون
     */
    private function getSalonRatings(int $salonId): array
    {
        $ratings = Rating::where('salon_id', $salonId)
            ->where('is_approved', true)
            ->get();

        $totalRatings = $ratings->count();
        $averageRating = $totalRatings > 0 ? round($ratings->avg('rating'), 1) : 0;

        $ratingDistribution = [
            5 => $ratings->where('rating', 5)->count(),
            4 => $ratings->where('rating', 4)->count(),
            3 => $ratings->where('rating', 3)->count(),
            2 => $ratings->where('rating', 2)->count(),
            1 => $ratings->where('rating', 1)->count(),
        ];

        $recentRatings = Rating::where('salon_id', $salonId)
            ->where('is_approved', true)
            ->with('customer')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($rating) {
                return [
                    'id' => $rating->id,
                    'customer_name' => $rating->customer->name,
                    'rating' => $rating->rating,
                    'comment' => $rating->comment,
                    'created_at' => $rating->created_at->diffForHumans(),
                ];
            });

        return [
            'rating' => [
                'average' => $averageRating,
                'total' => $totalRatings,
                'distribution' => $ratingDistribution,
                'recent' => $recentRatings,
            ],
        ];
    }

    /**
     * جلب بيانات الحلاقين مع تقييماتهم
     */
    private function getBarbersDataWithRatings(Salon $salon): array
    {
        $barbers = [];
        foreach ($salon->barbers as $barber) {
            $barberRatings = $this->getBarberRatings($barber->id);

            $barbers[] = [
                'id' => $barber->id,
                'name' => $barber->name,
                'phone' => $barber->phone,
                'avatar' => $barber->getAvatarUrlAttribute(),
                'services_count' => $barber->barberServices()->count(),
                'min_price' => $barber->barberServices()->min('price') ?? 0,
                'rating' => $barberRatings['rating'],
            ];
        }
        return $barbers;
    }

    /**
     * جلب تقييمات الحلاق
     */
    private function getBarberRatings(int $barberId): array
    {
        $ratings = Rating::where('barber_id', $barberId)
            ->where('is_approved', true)
            ->get();

        $totalRatings = $ratings->count();
        $averageRating = $totalRatings > 0 ? round($ratings->avg('rating'), 1) : 0;

        $ratingDistribution = [
            5 => $ratings->where('rating', 5)->count(),
            4 => $ratings->where('rating', 4)->count(),
            3 => $ratings->where('rating', 3)->count(),
            2 => $ratings->where('rating', 2)->count(),
            1 => $ratings->where('rating', 1)->count(),
        ];

        $recentRatings = Rating::where('barber_id', $barberId)
            ->where('is_approved', true)
            ->with('customer')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($rating) {
                return [
                    'id' => $rating->id,
                    'customer_name' => $rating->customer->name,
                    'rating' => $rating->rating,
                    'comment' => $rating->comment,
                    'created_at' => $rating->created_at->diffForHumans(),
                ];
            });

        return [
            'rating' => [
                'average' => $averageRating,
                'total' => $totalRatings,
                'distribution' => $ratingDistribution,
                'recent' => $recentRatings,
            ],
        ];
    }

    /**
     * حساب المسافة بين نقطتين (بالكيلومتر)
     */
    private function calculateDistance(Salon $salon, float $latitude, float $longitude): ?float
    {
        if (!$salon->latitude || !$salon->longitude) {
            return null;
        }

        $theta = $longitude - $salon->longitude;
        $dist = sin(deg2rad($latitude)) * sin(deg2rad($salon->latitude)) +
                cos(deg2rad($latitude)) * cos(deg2rad($salon->latitude)) *
                cos(deg2rad($theta));

        $dist = acos($dist);
        $dist = rad2deg($dist);
        $km = $dist * 60 * 1.1515 * 1.609344;

        return round($km, 1);
    }

    /**
     * الحصول على أقل سعر للخدمات في الصالون
     */
    private function getMinPrice(Salon $salon): float
    {
        $barberIds = $salon->barbers()->pluck('users.id')->toArray();

        $minPrice = BarberService::whereIn('barber_id', $barberIds)
            ->where('is_active', true)
            ->min('price');

        return $minPrice ?? 0;
    }

    /**
     * تنسيق أوقات العمل
     */
    private function getWorkingHoursFormatted(Salon $salon): array
    {
        $workingHours = $salon->workingHours()
            ->where('is_open', true)
            ->orderByRaw("FIELD(day_of_week, 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday')")
            ->get();

        $result = [];
        foreach ($workingHours as $hour) {
            $result[] = [
                'day' => $hour->day_of_week,
                'day_ar' => $this->daysInArabic[$hour->day_of_week],
                'is_open' => true,
                'start' => $hour->shift1_start,
                'end' => $hour->shift1_end,
                'hours_text' => $hour->shift1_start . ' - ' . $hour->shift1_end,
            ];
        }

        return $result;
    }

    /**
     * جلب خدمات الصالون
     */
    private function getSalonServices(Salon $salon): array
    {
        $barberIds = $salon->barbers()->pluck('users.id')->toArray();

        $services = BarberService::whereIn('barber_id', $barberIds)
            ->where('is_active', true)
            ->select('id', 'name', 'price', 'description', 'duration_minutes')
            ->orderBy('name', 'asc')
            ->get();

        $uniqueServices = $services->groupBy('name')->map(function ($group) {
            return $group->first();
        })->take(10);

        return $uniqueServices->map(function($service) {
            return [
                'id' => $service->id,
                'name' => $service->name,
                'price' => (float) $service->price,
                'duration_minutes' => $service->duration_minutes,
                'description' => $service->description ?? ''
            ];
        })->values()->toArray();
    }

    /**
     * الحصول على اسم اليوم بالعربية
     */
    private function getArabicDay(string $day): string
    {
        return $this->daysInArabic[strtolower($day)] ?? $day;
    }
}
