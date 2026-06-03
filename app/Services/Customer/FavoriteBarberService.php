<?php
// app/Services/Customer/FavoriteBarberService.php

namespace App\Services\Customer;

use App\Models\User;
use App\Services\AuthResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FavoriteBarberService
{
    /**
     * إضافة حلاق إلى المفضلة
     */
    public function addFavorite(User $customer, int $barberId): AuthResult
    {
        try {
            // التحقق من أن المستخدم عميل
            if (!$customer->hasRole('customer')) {
                return AuthResult::error('هذه الخدمة متاحة للعملاء فقط', null, 403);
            }

            // التحقق من وجود الحلاق
            $barber = User::where('id', $barberId)
                ->whereHas('roles', function ($q) {
                    $q->where('name', 'barber');
                })
                ->first();

            if (!$barber) {
                return AuthResult::error('الحلاق غير موجود', null, 404);
            }

            // منع إضافة النفس كمفضل
            if ($customer->id == $barberId) {
                return AuthResult::error('لا يمكن إضافة نفسك كمفضل', null, 400);
            }

            // إضافة إلى المفضلة
            $exists = $customer->favoriteBarbers()->where('barber_id', $barberId)->exists();

            if ($exists) {
                return AuthResult::error('هذا الحلاق موجود بالفعل في قائمة المفضلين', null, 400);
            }

            $customer->favoriteBarbers()->attach($barberId);

            Log::info('Barber added to favorites', [
                'customer_id' => $customer->id,
                'barber_id' => $barberId,
                'barber_name' => $barber->name
            ]);

            return AuthResult::success('تم إضافة الحلاق إلى المفضلة بنجاح', [
                'barber_id' => $barberId,
                'barber_name' => $barber->name,
                'is_favorite' => true
            ]);

        } catch (\Exception $e) {
            Log::error('Add favorite error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء إضافة الحلاق إلى المفضلة', null, 500);
        }
    }

    /**
     * إزالة حلاق من المفضلة
     */
    public function removeFavorite(User $customer, int $barberId): AuthResult
    {
        try {
            if (!$customer->hasRole('customer')) {
                return AuthResult::error('هذه الخدمة متاحة للعملاء فقط', null, 403);
            }

            $exists = $customer->favoriteBarbers()->where('barber_id', $barberId)->exists();

            if (!$exists) {
                return AuthResult::error('هذا الحلاق غير موجود في قائمة المفضلين', null, 404);
            }

            $customer->favoriteBarbers()->detach($barberId);

            Log::info('Barber removed from favorites', [
                'customer_id' => $customer->id,
                'barber_id' => $barberId
            ]);

            return AuthResult::success('تم إزالة الحلاق من المفضلة بنجاح', [
                'barber_id' => $barberId,
                'is_favorite' => false
            ]);

        } catch (\Exception $e) {
            Log::error('Remove favorite error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء إزالة الحلاق من المفضلة', null, 500);
        }
    }

    /**
     * جلب قائمة الحلاقين المفضلين لدى العميل
     */
public function getFavorites(User $customer, int $perPage = 10): AuthResult
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
            ->paginate($perPage);

        $salons = collect($favorites->items())->map(function ($salon) {
            // ... تنسيق بيانات الصالون ...
            return [ /* بيانات الصالون */ ];
        });

        return AuthResult::success('تم جلب قائمة الصالونات المفضلة بنجاح', [
            'salons' => $salons,
            'pagination' => [
                'current_page' => $favorites->currentPage(),
                'last_page' => $favorites->lastPage(),
                'per_page' => $favorites->perPage(),
                'total' => $favorites->total(),
                'next_page_url' => $favorites->nextPageUrl(),
                'prev_page_url' => $favorites->previousPageUrl(),
            ]
        ]);

    } catch (\Exception $e) {
        Log::error('Get favorite salons error: ' . $e->getMessage());
        return AuthResult::error('حدث خطأ أثناء جلب قائمة الصالونات المفضلة', $e->getMessage(), 500);
    }
}

    /**
     * التحقق مما إذا كان الحلاق مفضلاً
     */
    public function checkFavorite(User $customer, int $barberId): AuthResult
    {
        try {
            if (!$customer->hasRole('customer')) {
                return AuthResult::error('هذه الخدمة متاحة للعملاء فقط', null, 403);
            }

            $isFavorite = $customer->isFavoriteBarber($barberId);

            return AuthResult::success('تم التحقق بنجاح', [
                'barber_id' => $barberId,
                'is_favorite' => $isFavorite
            ]);

        } catch (\Exception $e) {
            Log::error('Check favorite error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء التحقق', null, 500);
        }
    }
}
