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
/**
 * جلب إحصائيات الصالون وعدد الحلاقين والإشعارات
 */
public function getBarbersCount(User $salonOwner): AuthResult
{
    try {
        $salon = $salonOwner->ownedSalon;

        if (!$salon) {
            return AuthResult::error('لا يوجد صالون تابع لك', null, 404);
        }

        // ===================== التواريخ =====================
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        // ===================== الإشعارات (عدد فقط) =====================
        $notificationsCount = [
            'total' => 0,
            'unread' => 0,
        ];

        try {
            $notificationsCount = [
                'total' => $salonOwner->notifications()->count(),
                'unread' => $salonOwner->unreadNotifications()->count(),
            ];
        } catch (\Exception $e) {
            // تجاهل الخطأ إذا كان جدول الإشعارات غير موجود
        }

        // ===================== إيرادات اليوم =====================
        $todayRevenue = Appointment::where('salon_id', $salon->id)
            ->where('status', 'completed')
            ->whereDate('appointment_date', $today)
            ->sum('total_price');

        $yesterdayRevenue = Appointment::where('salon_id', $salon->id)
            ->where('status', 'completed')
            ->whereDate('appointment_date', $yesterday)
            ->sum('total_price');

        $weeklyRevenue = Appointment::where('salon_id', $salon->id)
            ->where('status', 'completed')
            ->whereBetween('appointment_date', [$startOfWeek, $endOfWeek])
            ->sum('total_price');

        $monthlyRevenue = Appointment::where('salon_id', $salon->id)
            ->where('status', 'completed')
            ->whereBetween('appointment_date', [$startOfMonth, $endOfMonth])
            ->sum('total_price');

        $totalRevenue = Appointment::where('salon_id', $salon->id)
            ->where('status', 'completed')
            ->sum('total_price');

        // ===================== حجوزات اليوم =====================
        $todayTotalAppointments = Appointment::where('salon_id', $salon->id)
            ->whereDate('appointment_date', $today)
            ->count();

        $todayCompletedAppointments = Appointment::where('salon_id', $salon->id)
            ->where('status', 'completed')
            ->whereDate('appointment_date', $today)
            ->count();

        $todayCancelledAppointments = Appointment::where('salon_id', $salon->id)
            ->where('status', 'cancelled')
            ->whereDate('appointment_date', $today)
            ->count();

        $todayPendingAppointments = Appointment::where('salon_id', $salon->id)
            ->where('status', 'pending')
            ->whereDate('appointment_date', $today)
            ->count();

        $todayConfirmedAppointments = Appointment::where('salon_id', $salon->id)
            ->where('status', 'confirmed')
            ->whereDate('appointment_date', $today)
            ->count();

        // ===================== حجوزات الأمس =====================
        $yesterdayTotalAppointments = Appointment::where('salon_id', $salon->id)
            ->whereDate('appointment_date', $yesterday)
            ->count();

        $yesterdayCompletedAppointments = Appointment::where('salon_id', $salon->id)
            ->where('status', 'completed')
            ->whereDate('appointment_date', $yesterday)
            ->count();

        $yesterdayCancelledAppointments = Appointment::where('salon_id', $salon->id)
            ->where('status', 'cancelled')
            ->whereDate('appointment_date', $yesterday)
            ->count();

        $yesterdayPendingAppointments = Appointment::where('salon_id', $salon->id)
            ->where('status', 'pending')
            ->whereDate('appointment_date', $yesterday)
            ->count();

        $yesterdayConfirmedAppointments = Appointment::where('salon_id', $salon->id)
            ->where('status', 'confirmed')
            ->whereDate('appointment_date', $yesterday)
            ->count();

        // ===================== النسب المئوية =====================
        $completionRate = $todayTotalAppointments > 0
            ? round(($todayCompletedAppointments / $todayTotalAppointments) * 100, 1)
            : 0;

        $revenueChange = $yesterdayRevenue > 0
            ? round((($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100, 1)
            : ($todayRevenue > 0 ? 100 : 0);

        $appointmentsChange = $yesterdayTotalAppointments > 0
            ? round((($todayTotalAppointments - $yesterdayTotalAppointments) / $yesterdayTotalAppointments) * 100, 1)
            : ($todayTotalAppointments > 0 ? 100 : 0);

        // ===================== معلومات الصالون =====================
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

        // ===================== الحلاقين =====================
        $barbers = $salon->barbers()
            ->select('users.id', 'users.name', 'users.phone', 'users.is_active', 'users.created_at')
            ->get()
            ->map(function($barber) {
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

        $totalBarbers = $barbers->count();
        $activeBarbers = $barbers->where('is_active', true)->count();
        $inactiveBarbers = $totalBarbers - $activeBarbers;

        // ===================== الرد النهائي =====================
        return AuthResult::success('تم جلب بيانات الصالون بنجاح', [
            // إحصائيات الإشعارات
            'notifications_statistics' => $notificationsCount,

            // معلومات الصالون
            'salon' => $salonInfo,

            // الحلاقين
            'barbers' => $barbers,
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
            ],

            // إحصائيات حجوزات اليوم
            'appointments_statistics' => [
                'today_total' => $todayTotalAppointments,
                'today_completed' => $todayCompletedAppointments,
                'today_cancelled' => $todayCancelledAppointments,
                'today_pending' => $todayPendingAppointments,
                'today_confirmed' => $todayConfirmedAppointments,
                'completion_rate' => $completionRate,
            ],

            // إحصائيات حجوزات الأمس
            'appointments_statistics_yesterday' => [
                'yesterday_total' => $yesterdayTotalAppointments,
                'yesterday_completed' => $yesterdayCompletedAppointments,
                'yesterday_cancelled' => $yesterdayCancelledAppointments,
                'yesterday_pending' => $yesterdayPendingAppointments,
                'yesterday_confirmed' => $yesterdayConfirmedAppointments,
            ],

            // نسبة تغير الحجوزات
            'appointments_change_percentage' => $appointmentsChange,
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
