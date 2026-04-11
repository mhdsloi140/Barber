<?php
// app/Services/Salon/DashboardService.php

namespace App\Services\Salon;

use App\Models\User;
use App\Services\AuthResult;
use Illuminate\Support\Facades\Log;

class DashboardService
{
    /**
     * جلب عدد الحلاقين التابعين للصالون
     */
    public function getBarbersCount(User $salonOwner): AuthResult
    {
        try {
            $salon = $salonOwner->ownedSalon;
            if (!$salon) {
                return AuthResult::error('لا يوجد صالون تابع لك', null, 404);
            }
            $totalBarbers = $salon->barbers()->count();
            $activeBarbers = $salon->barbers()
                ->where('users.is_active', true)
                ->count();
            $inactiveBarbers = $totalBarbers - $activeBarbers;
            return AuthResult::success('تم جلب عدد الحلاقين بنجاح', [
                'total_barbers' => $totalBarbers,
                'active_barbers' => $activeBarbers,
                'inactive_barbers' => $inactiveBarbers,
                'day'=>'10',
                'Reservations'=>'10',
                'notifications'=>'10',
            ]);

        } catch (\Exception $e) {
            Log::error('Get barbers count error: ' . $e->getMessage());

            return AuthResult::error(
                'حدث خطأ أثناء جلب عدد الحلاقين',
                config('app.debug') ? $e->getMessage() : null,
                500
            );
        }
    }
}
