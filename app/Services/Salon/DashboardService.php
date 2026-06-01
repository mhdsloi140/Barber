<?php
// app/Services/Salon/DashboardService.php

namespace App\Services\Salon;

use App\Models\Appointment;
use App\Models\User;
use App\Services\AuthResult;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DashboardService
{
    /**
     * جلب معلومات الصالون وقائمة الحلاقين
     */
    /**
 * اسم اليوم بالعربية
 */
private function getArabicDayName(string $day): string
{
    $days = [
        'Sunday' => 'الأحد',
        'Monday' => 'الإثنين',
        'Tuesday' => 'الثلاثاء',
        'Wednesday' => 'الأربعاء',
        'Thursday' => 'الخميس',
        'Friday' => 'الجمعة',
        'Saturday' => 'السبت',
    ];
    return $days[$day] ?? $day;
}
   public function getBarbersCount(User $salonOwner): AuthResult
{
    try {
        $salon = $salonOwner->ownedSalon;

        if (!$salon) {
            return AuthResult::error('لا يوجد صالون تابع لك', null, 404);
        }

        // ===================== إحصائيات اليوم =====================
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        // إيرادات اليوم (المكتملة فقط)
        $todayRevenue = Appointment::where('salon_id', $salon->id)
            ->where('status', 'completed')
            ->whereDate('appointment_date', $today)
            ->sum('total_price');

        // إيرادات الأمس (للمقارنة)
        $yesterdayRevenue = Appointment::where('salon_id', $salon->id)
            ->where('status', 'completed')
            ->whereDate('appointment_date', $yesterday)
            ->sum('total_price');

        // إيرادات هذا الأسبوع
        $weeklyRevenue = Appointment::where('salon_id', $salon->id)
            ->where('status', 'completed')
            ->whereBetween('appointment_date', [$startOfWeek, $endOfWeek])
            ->sum('total_price');

        // إيرادات هذا الشهر
        $monthlyRevenue = Appointment::where('salon_id', $salon->id)
            ->where('status', 'completed')
            ->whereBetween('appointment_date', [$startOfMonth, $endOfMonth])
            ->sum('total_price');

        // إجمالي الإيرادات الكلي
        $totalRevenue = Appointment::where('salon_id', $salon->id)
            ->where('status', 'completed')
            ->sum('total_price');

        // ===================== الحجوزات اليومية =====================

        // إجمالي حجوزات اليوم
        $todayTotalAppointments = Appointment::where('salon_id', $salon->id)
            ->whereDate('appointment_date', $today)
            ->count();

        // الحجوزات المكتملة اليوم
        $todayCompletedAppointments = Appointment::where('salon_id', $salon->id)
            ->where('status', 'completed')
            ->whereDate('appointment_date', $today)
            ->count();

        // الحجوزات الملغية اليوم
        $todayCancelledAppointments = Appointment::where('salon_id', $salon->id)
            ->where('status', 'cancelled')
            ->whereDate('appointment_date', $today)
            ->count();

        // الحجوزات قيد الانتظار اليوم
        $todayPendingAppointments = Appointment::where('salon_id', $salon->id)
            ->where('status', 'pending')
            ->whereDate('appointment_date', $today)
            ->count();

        // الحجوزات المؤكدة اليوم
        $todayConfirmedAppointments = Appointment::where('salon_id', $salon->id)
            ->where('status', 'confirmed')
            ->whereDate('appointment_date', $today)
            ->count();

        // نسبة الإنجاز اليومية
        $completionRate = $todayTotalAppointments > 0
            ? round(($todayCompletedAppointments / $todayTotalAppointments) * 100, 1)
            : 0;

        // نسبة التغير في الإيرادات مقارنة بالأمس
        $revenueChange = $yesterdayRevenue > 0
            ? round((($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100, 1)
            : ($todayRevenue > 0 ? 100 : 0);

        // معلومات الصالون
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

        // جلب جميع الحلاقين التابعين للصالون
        $barbers = $salon->barbers()
            ->select('users.id', 'users.name', 'users.phone', 'users.is_active', 'users.created_at')
            ->get()
            ->map(function($barber) {
                // إحصائيات كل حلاق على حدة
                $barberTodayCompleted = Appointment::where('barber_id', $barber->id)
                    ->where('status', 'completed')
                    ->whereDate('appointment_date', Carbon::today())
                    ->count();

                $barberTodayRevenue = Appointment::where('barber_id', $barber->id)
                    ->where('status', 'completed')
                    ->whereDate('appointment_date', Carbon::today())
                    ->sum('total_price');

                return [
                    'id' => $barber->id,
                    'name' => $barber->name,
                    'phone' => $barber->phone,
                    'is_active' => $barber->is_active,
                    'joined_at' => $barber->created_at,
                    'today_completed' => $barberTodayCompleted,
                    'today_revenue' => (float) $barberTodayRevenue,
                ];
            });

        // عدد الحلاقين
        $totalBarbers = $barbers->count();
        $activeBarbers = $barbers->where('is_active', true)->count();
        $inactiveBarbers = $totalBarbers - $activeBarbers;

        return AuthResult::success('تم جلب بيانات الصالون بنجاح', [
            // معلومات الصالون
            'salon' => $salonInfo,

            // قائمة الحلاقين
            'barbers' => $barbers,

            // إحصائيات الحلاقين
            'barbers_statistics' => [
                'total_barbers' => $totalBarbers,
                'active_barbers' => $activeBarbers,
                'inactive_barbers' => $inactiveBarbers,
            ],

            // إحصائيات الإيرادات
            'revenue_statistics' => [
                'today' => (float) $todayRevenue,
                'yesterday' => (float) $yesterdayRevenue,
                'weekly' => (float) $weeklyRevenue,
                'monthly' => (float) $monthlyRevenue,
                'total' => (float) $totalRevenue,
                'change_percentage' => $revenueChange,
                // 'currency' => 'SAR',
            ],

            // إحصائيات الحجوزات اليومية
            'appointments_statistics' => [
                'today_total' => $todayTotalAppointments,
                'today_completed' => $todayCompletedAppointments,
                'today_cancelled' => $todayCancelledAppointments,
                'today_pending' => $todayPendingAppointments,
                'today_confirmed' => $todayConfirmedAppointments,
                'completion_rate' => $completionRate,
            ],

            // إحصائيات عامة للـ Dashboard
            'dashboard_stats' => [
                'day' => $today->format('Y-m-d'),
                'day_name' => $this->getArabicDayName($today->format('l')),
                'reservations' => $todayTotalAppointments,
                'completed_reservations' => $todayCompletedAppointments,
                'cancelled_reservations' => $todayCancelledAppointments,
                'pending_reservations' => $todayPendingAppointments,
                'notifications' => 0, // يمكن جلب من جدول الإشعارات
            ],
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
