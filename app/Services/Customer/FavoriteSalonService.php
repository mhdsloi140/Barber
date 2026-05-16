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

    /**
     * جلب قائمة الصالونات المفضلة لدى العميل
     */
    public function getFavorites(User $customer): AuthResult
    {
        try {
            if (!$customer->hasRole('customer')) {
                return AuthResult::error('هذه الخدمة متاحة للعملاء فقط', null, 403);
            }

            $favorites = $customer->favoriteSalons()
                ->select('salons.id', 'salons.name', 'salons.address', 'salons.phone', 'salons.latitude', 'salons.longitude')
                ->with(['owner' => function ($q) {
                    $q->select('id', 'name', 'phone');
                }])
                ->get()
                ->map(function ($salon) {
                    // جلب صور الصالون
                    $images = $salon->getMedia('salon_images')->map(function ($image) {
                        return [
                            'id' => $image->id,
                            'url' => $image->getUrl(),
                            'thumb' => $image->getUrl('thumb'),
                        ];
                    });

                    return [
                        'id' => $salon->id,
                        'name' => $salon->name,
                        'address' => $salon->address,
                        'phone' => $salon->phone,
                        'latitude' => $salon->latitude,
                        'longitude' => $salon->longitude,
                        'images' => $images,
                        'owner' => $salon->owner ? [
                            'id' => $salon->owner->id,
                            'name' => $salon->owner->name,
                            'phone' => $salon->owner->phone,
                        ] : null,
                        'is_favorite' => true,
                        'rating' => $salon->ratings()->avg('rating') ?? 0,
                    ];
                });

            return AuthResult::success('تم جلب قائمة الصالونات المفضلة بنجاح', [
                'count' => $favorites->count(),
                'salons' => $favorites
            ]);

        } catch (\Exception $e) {
            Log::error('Get favorite salons error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب قائمة الصالونات المفضلة', null, 500);
        }
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
