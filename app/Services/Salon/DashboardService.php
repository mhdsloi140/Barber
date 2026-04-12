<?php
// app/Services/Salon/DashboardService.php

namespace App\Services\Salon;

use App\Models\User;
use App\Services\AuthResult;
use Illuminate\Support\Facades\Log;

class DashboardService
{
    /**
     * جلب معلومات الصالون وقائمة الحلاقين
     */
    public function getBarbersCount(User $salonOwner): AuthResult
    {
        try {
            $salon = $salonOwner->ownedSalon;

            if (!$salon) {
                return AuthResult::error('لا يوجد صالون تابع لك', null, 404);
            }

            //  معلومات الصالون
            $salonInfo = [
                'id' => $salon->id,
                'name' => $salon->name,
                'owner_name' => $salonOwner->name,
                'address' => $salon->address,
                'phone' => $salon->phone,
                'latitude' => $salon->latitude,
                'longitude' => $salon->longitude,
                'is_active' => $salon->is_active,
                'image' => $salon->getMainImageUrlAttribute(),
                'images' => $salon->getImagesUrlsAttribute(),
                'created_at' => $salon->created_at,
            ];

            //  جلب جميع الحلاقين التابعين للصالون مع بياناتهم
            $barbers = $salon->barbers()
                ->select('users.id', 'users.name', 'users.phone', 'users.is_active', 'users.created_at')
                ->get()
                ->map(function($barber) {
                    return [
                        'id' => $barber->id,
                        'name' => $barber->name,
                        'phone' => $barber->phone,
                        'is_active' => $barber->is_active,
                        'joined_at' => $barber->created_at,
                    ];
                });

            //  عدد الحلاقين
            $totalBarbers = $barbers->count();
            $activeBarbers = $barbers->where('is_active', true)->count();
            $inactiveBarbers = $totalBarbers - $activeBarbers;

            return AuthResult::success('تم جلب بيانات الصالون بنجاح', [
                // معلومات الصالون
                'salon' => $salonInfo,

                // قائمة الحلاقين
                'barbers' => $barbers,

                // إحصائيات الحلاقين
                'statistics' => [
                    'total_barbers' => $totalBarbers,
                    'active_barbers' => $activeBarbers,
                    'inactive_barbers' => $inactiveBarbers,
                ],

                // إحصائيات إضافية (يمكن إضافتها لاحقاً)
                'day' => 0,
                'reservations' => 0, // يمكن جلب من جدول المواعيد
                'notifications' => 0, // يمكن جلب من جدول الإشعارات
            ]);

        } catch (\Exception $e) {
            Log::error('Get salon dashboard error: ' . $e->getMessage());

            return AuthResult::error(
                'حدث خطأ أثناء جلب بيانات الصالون',
                config('app.debug') ? $e->getMessage() : null,
                500
            );
        }
    }
}
