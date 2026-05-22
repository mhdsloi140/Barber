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

            // البحث حسب الاسم أو العنوان
            if (!empty($filters['search'])) {
                $query->where(function($q) use ($filters) {
                    $q->where('salons.name', 'like', '%' . $filters['search'] . '%')
                      ->orWhere('salons.address', 'like', '%' . $filters['search'] . '%');
                });
            }

            // حساب المسافة والترتيب حسب الأقرب
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

            // تنسيق البيانات للعرض مع الصور والتقييمات
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
     * عرض صالون محدد مع جميع الصور والتقييمات وأوقات الفراغ للحلاقين
     */
    public function getSalon($id, ?float $latitude = null, ?float $longitude = null, ?string $date = null, ?int $serviceId = null): AuthResult
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

            //  إضافة أوقات الفراغ للحلاقين إذا تم تحديد التاريخ
            if ($date) {
                $data['barbers_with_slots'] = $this->getBarbersWithAvailableSlots($salon, $date, $serviceId);
                $data['selected_date'] = $date;
                // $data['selected_service_id'] = $serviceId;
            }

            return AuthResult::success('تم جلب بيانات الصالون بنجاح', $data);

        } catch (\Exception $e) {
            Log::error('Get salon error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب بيانات الصالون', config('app.debug') ? $e->getMessage() : null, 500);
        }
    }

    /**
     * جلب أوقات الفراغ للحلاق في يوم محدد
     */
    public function getBarberAvailableSlots(int $barberId, string $date, ?int $serviceId = null): AuthResult
    {
        try {
            $barber = \App\Models\User::where('id', $barberId)
                ->whereHas('roles', function($q) {
                    $q->where('name', 'barber');
                })
                ->with(['salons', 'workingHours' => function($q) {
                    $q->where('is_open', true);
                }])
                ->first();

            if (!$barber) {
                return AuthResult::error('الحلاق غير موجود', null, 404);
            }

            $salon = $barber->salons->first();
            if (!$salon) {
                return AuthResult::error('هذا الحلاق لا يعمل في أي صالون', null, 404);
            }

            // جلب مدة الخدمة إذا تم تحديد خدمة
            $serviceDuration = null;
            $serviceName = null;
            $servicePrice = null;

            if ($serviceId) {
                $service = BarberService::where('id', $serviceId)
                    ->where('barber_id', $barberId)
                    ->first();

                if ($service) {
                    $serviceDuration = $service->duration_minutes;
                    $serviceName = $service->name;
                    $servicePrice = $service->price;
                }
            }

            // جلب يوم الأسبوع من التاريخ
            $dayOfWeek = strtolower(Carbon::parse($date)->format('l'));

            // جلب أوقات عمل الحلاق في هذا اليوم
            $barberWorkingHour = $barber->workingHours()
                ->where('day_of_week', $dayOfWeek)
                ->where('is_open', true)
                ->first();

            if (!$barberWorkingHour) {
                return AuthResult::success('الحلاق غير متوفر في هذا اليوم', [
                    'barber_id' => $barberId,
                    'barber_name' => $barber->name,
                    'date' => $date,
                    'day' => $this->getArabicDay($dayOfWeek),
                    'is_available' => false,
                    'available_slots' => [],
                    'message' => 'الحلاق غير متوفر في هذا اليوم'
                ]);
            }

            // وقت بداية ونهاية العمل
            $startTime = $barberWorkingHour->shift1_start;
            $endTime = $barberWorkingHour->shift1_end;

            // إذا لم يتم تحديد خدمة، لا يمكن حساب الفترات
            if (!$serviceDuration) {
                return AuthResult::success('الرجاء تحديد خدمة لحساب أوقات الفراغ', [
                    'barber_id' => $barberId,
                    'barber_name' => $barber->name,
                    'date' => $date,
                    'day' => $this->getArabicDay($dayOfWeek),
                    'working_hours' => [
                        'start' => $startTime,
                        'end' => $endTime,
                    ],
                    'message' => 'الرجاء تحديد خدمة أولاً',
                    'need_service' => true
                ]);
            }

            // جلب الحجوزات المؤكدة والقيد الانتظار لهذا الحلاق في التاريخ المحدد
            $bookedAppointments = Appointment::where('barber_id', $barberId)
                ->where('appointment_date', $date)
                ->whereIn('status', ['pending', 'confirmed'])
                ->get();

            // إنشاء جميع الفترات الزمنية الممكنة حسب مدة الخدمة
            $allSlots = $this->generateTimeSlots($startTime, $endTime, $serviceDuration);

            // إزالة الفترات المحجوزة
            $availableSlots = $this->filterBookedSlots($allSlots, $bookedAppointments, $serviceDuration);

            return AuthResult::success('تم جلب أوقات الفراغ بنجاح', [
                'barber_id' => $barberId,
                'barber_name' => $barber->name,
                'barber_avatar' => $barber->getAvatarUrlAttribute(),
                'salon_id' => $salon->id,
                'salon_name' => $salon->name,
                'date' => $date,
                'day' => $this->getArabicDay($dayOfWeek),
                'working_hours' => [
                    'start' => $startTime,
                    'end' => $endTime,
                ],
                'service' => [
                    'id' => $serviceId,
                    'name' => $serviceName,
                    'price' => $servicePrice,
                    'duration_minutes' => $serviceDuration,
                ],
                'available_slots' => $availableSlots,
                'available_count' => count($availableSlots),
            ]);

        } catch (\Exception $e) {
            Log::error('Get barber available slots error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب أوقات الفراغ', null, 500);
        }
    }

    /**
     * جلب أوقات الفراغ لحلاق معين بدون تحديد خدمة (لجميع الخدمات)
     */
    public function getBarberAvailableSlotsForAllServices(int $barberId, string $date): AuthResult
    {
        try {
            $barber = \App\Models\User::where('id', $barberId)
                ->whereHas('roles', function($q) {
                    $q->where('name', 'barber');
                })
                ->with(['salons', 'workingHours' => function($q) {
                    $q->where('is_open', true);
                }, 'barberServices'])
                ->first();

            if (!$barber) {
                return AuthResult::error('الحلاق غير موجود', null, 404);
            }

            $salon = $barber->salons->first();
            if (!$salon) {
                return AuthResult::error('هذا الحلاق لا يعمل في أي صالون', null, 404);
            }

            // جلب يوم الأسبوع من التاريخ
            $dayOfWeek = strtolower(Carbon::parse($date)->format('l'));

            // جلب أوقات عمل الحلاق في هذا اليوم
            $barberWorkingHour = $barber->workingHours()
                ->where('day_of_week', $dayOfWeek)
                ->where('is_open', true)
                ->first();

            if (!$barberWorkingHour) {
                return AuthResult::success('الحلاق غير متوفر في هذا اليوم', [
                    'barber_id' => $barberId,
                    'barber_name' => $barber->name,
                    'date' => $date,
                    'day' => $this->getArabicDay($dayOfWeek),
                    'is_available' => false,
                    'available_slots' => [],
                    'services' => [],
                    'message' => 'الحلاق غير متوفر في هذا اليوم'
                ]);
            }

            // وقت بداية ونهاية العمل
            $startTime = $barberWorkingHour->shift1_start;
            $endTime = $barberWorkingHour->shift1_end;

            // جلب خدمات الحلاق
            $services = $barber->barberServices()
                ->where('is_active', true)
                ->get()
                ->map(function($service) {
                    return [
                        'id' => $service->id,
                        'name' => $service->name,
                        'price' => (float) $service->price,
                        'duration_minutes' => $service->duration_minutes,
                    ];
                });

            return AuthResult::success('تم جلب بيانات الحلاق بنجاح', [
                'barber_id' => $barberId,
                'barber_name' => $barber->name,
                'barber_avatar' => $barber->getAvatarUrlAttribute(),
                'salon_id' => $salon->id,
                'salon_name' => $salon->name,
                'date' => $date,
                'day' => $this->getArabicDay($dayOfWeek),
                'working_hours' => [
                    'start' => $startTime,
                    'end' => $endTime,
                ],
                'services' => $services,
                'services_count' => $services->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Get barber available slots for all services error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب البيانات', null, 500);
        }
    }

    /**
     * جلب أوقات الفراغ للحلاقين في الصالون (لكل حلاق)
     */
    private function getBarbersWithAvailableSlots(Salon $salon, string $date, ?int $serviceId = null): array
    {
        $barbersWithSlots = [];

        foreach ($salon->barbers as $barber) {
            // جلب مدة الخدمة
            $serviceDuration = null;
            $serviceName = null;
            $servicePrice = null;

            if ($serviceId) {
                $service = BarberService::where('id', $serviceId)
                    ->where('barber_id', $barber->id)
                    ->first();

                if ($service) {
                    $serviceDuration = $service->duration_minutes;
                    $serviceName = $service->name;
                    $servicePrice = $service->price;
                }
            }

            // إذا لم يتم تحديد خدمة، استخدم أول خدمة للحلاق
            if (!$serviceDuration) {
                $firstService = $barber->barberServices()->where('is_active', true)->first();
                if ($firstService) {
                    $serviceDuration = $firstService->duration_minutes;
                    $serviceName = $firstService->name;
                    $servicePrice = $firstService->price;
                }
            }

            if (!$serviceDuration) {
                continue;
            }

            // جلب يوم الأسبوع من التاريخ
            $dayOfWeek = strtolower(Carbon::parse($date)->format('l'));

            // جلب أوقات عمل الحلاق في هذا اليوم
            $barberWorkingHour = $barber->workingHours()
                ->where('day_of_week', $dayOfWeek)
                ->where('is_open', true)
                ->first();

            if (!$barberWorkingHour) {
                continue;
            }

            $startTime = $barberWorkingHour->shift1_start;
            $endTime = $barberWorkingHour->shift1_end;

            // جلب الحجوزات في هذا اليوم
            $bookedAppointments = Appointment::where('barber_id', $barber->id)
                ->where('appointment_date', $date)
                ->whereIn('status', ['pending', 'confirmed'])
                ->get();

            // توليد الفترات المتاحة
            $allSlots = $this->generateTimeSlots($startTime, $endTime, $serviceDuration);
            $availableSlots = $this->filterBookedSlots($allSlots, $bookedAppointments, $serviceDuration);

            // جلب تقييمات الحلاق
            $barberRatings = $this->getBarberRatings($barber->id);

            $barbersWithSlots[] = [
                'id' => $barber->id,
                'name' => $barber->name,
                'phone' => $barber->phone,
                'avatar' => $barber->getAvatarUrlAttribute(),
                'services_count' => $barber->barberServices()->count(),
                'min_price' => $barber->barberServices()->min('price') ?? 0,
                'rating' => $barberRatings['rating'],
                'is_available' => !empty($availableSlots),
                'available_slots' => $availableSlots,
                'available_slots_count' => count($availableSlots),
            ];
        }

        return $barbersWithSlots;
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
                // 'duration_minutes' => $slotDuration,
                    // 'duration_minutes' => $slotDuration,
                ];
            }

            $current->addMinutes($slotDuration);
        }

        return $slots;
    }

    /**
     * إزالة الفترات المحجوزة من قائمة الفترات المتاحة
     */
    private function filterBookedSlots(array $allSlots, $bookedAppointments, int $slotDuration): array
    {
        $availableSlots = [];

        foreach ($allSlots as $slot) {
            $isBooked = false;
            $slotStart = Carbon::parse($slot['start']);
            $slotEnd = Carbon::parse($slot['end']);

            foreach ($bookedAppointments as $appointment) {
                $appointmentStart = Carbon::parse($appointment->appointment_time);
                $appointmentEnd = Carbon::parse($appointment->end_time);

                // التحقق من تداخل الفترات
                if ($slotStart->lt($appointmentEnd) && $slotEnd->gt($appointmentStart)) {
                    $isBooked = true;
                    break;
                }
            }

            if (!$isBooked) {
                $availableSlots[] = $slot;
            }
        }

        return $availableSlots;
    }

    /**
     * تنسيق بيانات الصالون مع جميع الصور والتقييمات
     */
    private function formatSalonDataWithImagesAndRatings(Salon $salon, ?float $latitude = null, ?float $longitude = null): array
    {
        // جلب جميع صور الصالون
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

        // جلب تقييمات الصالون
        $salonRatings = $this->getSalonRatings($salon->id);

        // حساب المسافة
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
