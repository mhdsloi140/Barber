<?php
// app/Services/Customer/SalonService.php

namespace App\Services\Customer;

use App\Models\Salon;
use App\Models\Rating;
use App\Models\WorkingHour;
use App\Services\AuthResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SalonService
{
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
     * عرض صالون محدد مع جميع الصور والتقييمات
     */
    public function getSalon($id, ?float $latitude = null, ?float $longitude = null): AuthResult
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

            return AuthResult::success('تم جلب بيانات الصالون بنجاح', $data);

        } catch (\Exception $e) {
            Log::error('Get salon error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب بيانات الصالون', config('app.debug') ? $e->getMessage() : null, 500);
        }
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

        return [
            'id' => $salon->id,
            'name' => $salon->name,
            'address' => $salon->address,
            'phone' => $salon->phone,
            'latitude' => $salon->latitude,
            'longitude' => $salon->longitude,
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
        // جلب جميع التقييمات للصالون
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

        // آخر 5 تقييمات للصالون
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
            // جلب تقييمات الحلاق
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

        // آخر 5 تقييمات للحلاق
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
     * جلب بيانات الحلاقين في الصالون (بدون تقييمات - للإصدارات السابقة)
     */
    private function getBarbersData(Salon $salon): array
    {
        $barbers = [];
        foreach ($salon->barbers as $barber) {
            $barbers[] = [
                'id' => $barber->id,
                'name' => $barber->name,
                'phone' => $barber->phone,
                'avatar' => $barber->getAvatarUrlAttribute(),
                'services_count' => $barber->barberServices()->count(),
                'min_price' => $barber->barberServices()->min('price') ?? 0,
            ];
        }
        return $barbers;
    }

    /**
     * حساب المسافة بين نقطتين (بالكيلومتر)
     */
    private function calculateDistance(Salon $salon, ?float $latitude, ?float $longitude): ?float
    {
        if (!$latitude || !$longitude || !$salon->latitude || !$salon->longitude) {
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

        $minPrice = \App\Models\BarberService::whereIn('barber_id', $barberIds)
            ->where('is_active', true)
            ->min('price');

        return $minPrice ?? 0;
    }

    /**
     * تنسيق أوقات العمل
     */
    private function getWorkingHoursFormatted(Salon $salon): array
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

        $workingHours = $salon->workingHours()
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
     * جلب خدمات الصالون
     */
    private function getSalonServices(Salon $salon): array
    {
        $barberIds = $salon->barbers()->pluck('users.id')->toArray();

        $services = \App\Models\BarberService::whereIn('barber_id', $barberIds)
            ->where('is_active', true)
            ->select('name', 'price', 'description', 'duration_minutes')
            ->distinct('name')
            ->limit(10)
            ->get();

        return $services->map(function($service) {
            return [
                'name' => $service->name,
                'price' => $service->price,
                'duration' => $service->duration_minutes,
                'description' => $service->description
            ];
        })->toArray();
    }
}
