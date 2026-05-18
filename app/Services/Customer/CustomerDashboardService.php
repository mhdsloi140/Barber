<?php
// app/Services/Customer/CustomerDashboardService.php

namespace App\Services\Customer;

use App\Models\User;
use App\Models\Appointment;
use App\Models\Advertisement;
use App\Services\AuthResult;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CustomerDashboardService
{
    /**
     * جلب الحجوزات والمفضلة والاعلانات للزبون
     */
    public function getDashboard(User $customer): AuthResult
    {
        try {
            if (!$customer->hasRole('customer')) {
                return AuthResult::error('هذه الخدمة متاحة للعملاء فقط', null, 403);
            }

            // 1. الحجوزات القادمة
            $upcomingAppointments = $this->getUpcomingAppointments($customer->id);

            // 2. الحجوزات السابقة
            $pastAppointments = $this->getPastAppointments($customer->id);

            // 3. الصالونات المفضلة
            $favoriteSalons = $this->getFavoriteSalons($customer);

            // 4. الحلاقين المفضلين
            $favoriteBarbers = $this->getFavoriteBarbers($customer);

            // 5. الاعلانات النشطة
            $activeAds = $this->getActiveAds();

            $data = [
                'upcoming_appointments' => $upcomingAppointments,
                'past_appointments' => $pastAppointments,
                'favorite_salons' => $favoriteSalons,
                'favorite_barbers' => $favoriteBarbers,
                'active_ads' => $activeAds,
            ];

            return AuthResult::success('تم جلب البيانات بنجاح', $data);

        } catch (\Exception $e) {
            Log::error('Get customer dashboard error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب البيانات', null, 500);
        }
    }

    /**
     * الحجوزات القادمة
     */
    private function getUpcomingAppointments(int $customerId)
    {
        $appointments = Appointment::where('customer_id', $customerId)
            ->where('status', '!=', 'cancelled')
            ->where('status', '!=', 'completed')
            ->where('appointment_date', '>=', Carbon::now()->toDateString())
            ->with(['salon', 'barber'])
            ->orderBy('appointment_date', 'asc')
            ->orderBy('appointment_time', 'asc')
            ->get();

        return $appointments->map(function ($appointment) {
            return $this->formatAppointment($appointment);
        });
    }

    /**
     * الحجوزات السابقة
     */
    private function getPastAppointments(int $customerId)
    {
        $appointments = Appointment::where('customer_id', $customerId)
            ->where(function ($q) {
                $q->where('status', 'completed')
                  ->orWhere('appointment_date', '<', Carbon::now()->toDateString())
                  ->orWhere('status', 'cancelled');
            })
            ->with(['salon', 'barber'])
            ->orderBy('appointment_date', 'desc')
            ->orderBy('appointment_time', 'desc')
            ->limit(10)
            ->get();

        return $appointments->map(function ($appointment) {
            return $this->formatAppointment($appointment);
        });
    }

    /**
     * الصالونات المفضلة
     */
    private function getFavoriteSalons(User $customer)
    {
        $favorites = $customer->favoriteSalons()
            ->select('salons.id', 'salons.name', 'salons.address', 'salons.phone')
            ->withCount('barbers')
            ->get()
            ->map(function ($salon) {
                $image = $salon->getFirstMediaUrl('salon_images', 'thumb');

                return [
                    'id' => $salon->id,
                    'name' => $salon->name,
                    'address' => $salon->address,
                    'phone' => $salon->phone,
                    'image' => $image,
                    'barbers_count' => $salon->barbers_count,
                    'rating' => round($salon->ratings()->avg('rating') ?? 0, 1),
                ];
            });

        return $favorites;
    }

    /**
     * الحلاقين المفضلين
     */
    private function getFavoriteBarbers(User $customer)
    {
        $favorites = $customer->favoriteBarbers()
            ->select('users.id', 'users.name', 'users.phone')
            ->with(['salons' => function ($q) {
                $q->select('salons.id', 'salons.name');
            }])
            ->get()
            ->map(function ($barber) {
                return [
                    'id' => $barber->id,
                    'name' => $barber->name,
                    'phone' => $barber->phone,
                    'avatar' => $barber->getAvatarUrlAttribute(),
                    'salon' => $barber->salons->first() ? [
                        'id' => $barber->salons->first()->id,
                        'name' => $barber->salons->first()->name,
                    ] : null,
                    'rating' => round($barber->ratingsReceived()->avg('rating') ?? 0, 1),
                ];
            });

        return $favorites;
    }

    /**
     * الاعلانات النشطة
     */
   /**
 * الاعلانات النشطة
 */
private function getActiveAds()
{
    $ads = Advertisement::where('is_active', true)
        ->where(function ($query) {
            $query->whereNull('starts_at')
                  ->orWhere('starts_at', '<=', now());
        })
        ->where(function ($query) {
            $query->whereNull('ends_at')
                  ->orWhere('ends_at', '>=', now());
        })
        ->orderBy('sort_order', 'asc')
        ->get();

    // جلب الصور لكل إعلان
    $ads->load('media'); // تحميل علاقة media مسبقاً

    return $ads->map(function ($ad) {
        $images = collect();

        // محاولة جلب الصور من Collection 'ad_images'
        $mediaItems = $ad->getMedia('ad_images');

        if ($mediaItems->isEmpty()) {
            // محاولة جلب من collection الافتراضي
            $mediaItems = $ad->getMedia();
        }

        foreach ($mediaItems as $image) {
            $images->push([
                'id' => $image->id,
                'url' => $image->getUrl(),
                'thumb' => $image->getUrl('thumb'),
                'medium' => $image->getUrl('medium'),
            ]);
        }

        return [
            'id' => $ad->id,
            'images' => $images,
            'first_image' => $images->isNotEmpty() ? $images->first()['url'] : null,
        ];
    });
}
    /**
     * تنسيق بيانات الحجز
     */
    private function formatAppointment($appointment): array
    {
        return [
            'id' => $appointment->id,
            'salon' => [
                'id' => $appointment->salon->id,
                'name' => $appointment->salon->name,
                'address' => $appointment->salon->address,
            ],
            'barber' => [
                'id' => $appointment->barber->id,
                'name' => $appointment->barber->name,
                'avatar' => $appointment->barber->getAvatarUrlAttribute(),
            ],
            'total_price' => (float) $appointment->total_price,
            'date' => $appointment->appointment_date,
            'time' => $appointment->appointment_time,
            'status' => $appointment->status,
            'status_text' => $this->getStatusText($appointment->status),
        ];
    }

    /**
     * نص الحالة بالعربية
     */
    private function getStatusText(string $status): string
    {
        return match ($status) {
            'pending' => 'قيد الانتظار',
            'confirmed' => 'مؤكد',
            'completed' => 'مكتمل',
            'cancelled' => 'ملغي',
            default => $status,
        };
    }
}
