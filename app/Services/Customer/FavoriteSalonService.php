<?php


namespace App\Services\Customer;

use App\Models\User;
use App\Models\Salon;
use App\Services\AuthResult;
use Illuminate\Support\Facades\Log;

class FavoriteSalonService
{
    /**
     * إضافة صالون إلى المفضلة
     */
    public function addFavorite(User $customer, int $salonId): AuthResult
    {
        try {
            if (!$customer->hasRole('customer')) {
                return AuthResult::error('هذه الخدمة متاحة للعملاء فقط', null, 403);
            }

            $salon = Salon::find($salonId);

            if (!$salon) {
                return AuthResult::error('الصالون غير موجود', null, 404);
            }

            $exists = $customer->favoriteSalons()->where('salon_id', $salonId)->exists();

            if ($exists) {
                return AuthResult::error('هذا الصالون موجود بالفعل في قائمة المفضلين', null, 400);
            }

            $customer->favoriteSalons()->attach($salonId);

            Log::info('Salon added to favorites', [
                'customer_id' => $customer->id,
                'salon_id' => $salonId,
                'salon_name' => $salon->name
            ]);

            return AuthResult::success('تم إضافة الصالون إلى المفضلة بنجاح', [
                'salon_id' => $salonId,
                'salon_name' => $salon->name,
                'is_favorite' => true
            ]);

        } catch (\Exception $e) {
            Log::error('Add favorite salon error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء إضافة الصالون إلى المفضلة', null, 500);
        }
    }

    /**
     * إزالة صالون من المفضلة
     */
    public function removeFavorite(User $customer, int $salonId): AuthResult
    {
        try {
            if (!$customer->hasRole('customer')) {
                return AuthResult::error('هذه الخدمة متاحة للعملاء فقط', null, 403);
            }

            $exists = $customer->favoriteSalons()->where('salon_id', $salonId)->exists();

            if (!$exists) {
                return AuthResult::error('هذا الصالون غير موجود في قائمة المفضلين', null, 404);
            }

            $customer->favoriteSalons()->detach($salonId);

            Log::info('Salon removed from favorites', [
                'customer_id' => $customer->id,
                'salon_id' => $salonId
            ]);

            return AuthResult::success('تم إزالة الصالون من المفضلة بنجاح', [
                'salon_id' => $salonId,
                'is_favorite' => false
            ]);

        } catch (\Exception $e) {
            Log::error('Remove favorite salon error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء إزالة الصالون من المفضلة', null, 500);
        }
    }

public function getFavorites(User $customer): AuthResult
{
    try {
        if (!$customer->hasRole('customer')) {
            return AuthResult::error('هذه الخدمة متاحة للعملاء فقط', null, 403);
        }

        $favorites = $customer->favoriteSalons()
            ->select('salons.*')
            ->with([
                'owner' => function ($q) {
                    $q->select('id', 'name', 'phone');
                }
            ])
            ->with(['ratings' => function ($q) {
                $q->select('id', 'salon_id', 'rating', 'comment', 'created_at', 'customer_id')
                    ->with('customer:id,name,phone');
            }])
            ->withAvg('ratings', 'rating')
            ->withCount('ratings as reviews_count')
            ->get()
            ->map(function ($salon) {
                $images = $salon->getMedia('salon_images')->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'url' => $image->getUrl(),
                    ];
                });

                // حساب توزيع التقييمات
                $ratingDistribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];

                foreach ($salon->ratings as $rating) {
                    $ratingDistribution[(int) $rating->rating]++;
                }

                // آخر 3 تقييمات
                $latestRatings = $salon->ratings()
                    ->with('customer:id,name,phone')
                    ->latest()
                    ->take(3)
                    ->get()
                    ->map(function ($rating) {
                        return [
                            'id' => $rating->id,
                            'rating' => $rating->rating,
                            'comment' => $rating->comment,
                            'user_name' => $rating->customer->name ?? 'مستخدم',
                            'created_at' => $rating->created_at->diffForHumans(),
                        ];
                    });

                return [
                    'id' => $salon->id,
                    'name' => $salon->name,
                    'address' => $salon->address,
                    'phone' => $salon->phone,
                    'latitude' => $salon->latitude,
                    'longitude' => $salon->longitude,
                    'description' => $salon->description,
                    'images' => $images,
                    'cover_image' => $salon->getFirstMediaUrl('salon_cover'),
                    'owner' => $salon->owner ? [
                        'id' => $salon->owner->id,
                        'name' => $salon->owner->name,
                        'phone' => $salon->owner->phone,
                    ] : null,
                    'is_favorite' => true,
                    'working_hours' => $salon->working_hours,
                    'is_active' => $salon->is_active,
                    'barbers_count' => $salon->barbers()->count(),
                    'rating' => [
                        'average' => round($salon->ratings_avg_rating ?? 0, 1),
                        'total_count' => $salon->reviews_count ?? 0,
                        'distribution' => $ratingDistribution,
                        'latest_reviews' => $latestRatings,
                    ],
                ];
            });

        return AuthResult::success('تم جلب قائمة الصالونات المفضلة بنجاح', [
            'count' => $favorites->count(),
            'salons' => $favorites
        ]);

    } catch (\Exception $e) {
        Log::error('Get favorite salons error: ' . $e->getMessage());
        return AuthResult::error('حدث خطأ أثناء جلب قائمة الصالونات المفضلة: ' . $e->getMessage(), null, 500);
    }
}
    /**
     * التحقق إذا كان الصالون مفتوحاً الآن
     */
    private function isSalonOpen($salon): bool
    {
        if (!$salon->working_hours) {
            return true; // افتراضي
        }

        $now = now();
        $day = strtolower($now->format('l')); // monday, tuesday, etc.
        $currentTime = $now->format('H:i');

        $hours = json_decode($salon->working_hours, true);

        if (!isset($hours[$day]) || !$hours[$day]['is_open']) {
            return false;
        }

        return $currentTime >= $hours[$day]['open'] && $currentTime <= $hours[$day]['close'];
    }

    /**
     * الحصول على أوقات العمل الافتراضية
     */
    private function getDefaultWorkingHours(): array
    {
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $default = [];

        foreach ($days as $day) {
            $default[$day] = [
                'is_open' => true,
                'open' => '09:00',
                'close' => '21:00',
            ];
        }

        return $default;
    }
    /**
     * التحقق مما إذا كان الصالون مفضلاً
     */
    public function checkFavorite(User $customer, int $salonId): AuthResult
    {
        try {
            if (!$customer->hasRole('customer')) {
                return AuthResult::error('هذه الخدمة متاحة للعملاء فقط', null, 403);
            }

            $isFavorite = $customer->isFavoriteSalon($salonId);

            return AuthResult::success('تم التحقق بنجاح', [
                'salon_id' => $salonId,
                'is_favorite' => $isFavorite
            ]);

        } catch (\Exception $e) {
            Log::error('Check favorite salon error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء التحقق', null, 500);
        }
    }

    /**
     * جلب الصالونات المفضلة مع إحصائيات
     */
    public function getFavoritesWithStats(User $customer): AuthResult
    {
        try {
            if (!$customer->hasRole('customer')) {
                return AuthResult::error('هذه الخدمة متاحة للعملاء فقط', null, 403);
            }

            $favorites = $customer->favoriteSalons()
                ->select('salons.id', 'salons.name', 'salons.address')
                ->get()
                ->map(function ($salon) {
                    return [
                        'id' => $salon->id,
                        'name' => $salon->name,
                        'address' => $salon->address,
                        'barbers_count' => $salon->barbers()->count(),
                        'rating' => round($salon->ratings()->avg('rating') ?? 0, 1),
                        'total_ratings' => $salon->ratings()->count(),
                    ];
                });

            return AuthResult::success('تم جلب إحصائيات الصالونات المفضلة بنجاح', [
                'total_favorites' => $favorites->count(),
                'salons' => $favorites
            ]);

        } catch (\Exception $e) {
            Log::error('Get favorite salons stats error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب الإحصائيات', null, 500);
        }
    }
}
