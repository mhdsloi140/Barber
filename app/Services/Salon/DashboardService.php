<?php
// app/Services/Salon/DashboardService.php

namespace App\Services\Salon;

use App\Models\Appointment;
use App\Models\User;
use App\Services\AuthResult;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardService
{
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


    private function formatTime($time): ?string
    {
        if (!$time) return null;
        if ($time instanceof Carbon) {
            return $time->format('H:i');
        }
        return Carbon::parse($time)->format('H:i');
    }


    public function getBarbersCount(User $salonOwner): AuthResult
    {
        try {
            $salon = $salonOwner->ownedSalon;

            if (!$salon) {
                return AuthResult::error('لا يوجد صالون تابع لك', null, 404);
            }

            $today = Carbon::today()->toDateString();
            $yesterday = Carbon::yesterday()->toDateString();
            $startOfWeek = Carbon::now()->startOfWeek()->toDateString();
            $endOfWeek = Carbon::now()->endOfWeek()->toDateString();
            $startOfMonth = Carbon::now()->startOfMonth()->toDateString();
            $endOfMonth = Carbon::now()->endOfMonth()->toDateString();

            $salonId = $salon->id;

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
            }

            $stats = $this->getAppointmentStatistics($salonId, $today, $yesterday, $startOfWeek, $endOfWeek, $startOfMonth, $endOfMonth);

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

            $barbers = $this->getBarbersWithStats($salon, $today);

            $totalBarbers = $barbers->count();
            $activeBarbers = $barbers->where('is_active', true)->count();
            $inactiveBarbers = $totalBarbers - $activeBarbers;

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

                'revenue_statistics' => $stats['revenue'],

                'appointments_statistics' => $stats['today'],

                'appointments_statistics_yesterday' => $stats['yesterday'],

                'appointments_change_percentage' => $stats['change_percentage'],
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


    private function getAppointmentStatistics(int $salonId, string $today, string $yesterday, string $startOfWeek, string $endOfWeek, string $startOfMonth, string $endOfMonth): array
    {
        $results = DB::table('appointments')
            ->where('salon_id', $salonId)
            ->where('status', 'completed')
            ->selectRaw("
                SUM(CASE WHEN DATE(appointment_date) = ? THEN total_price ELSE 0 END) as today_revenue,
                SUM(CASE WHEN DATE(appointment_date) = ? THEN total_price ELSE 0 END) as yesterday_revenue,
                SUM(CASE WHEN appointment_date BETWEEN ? AND ? THEN total_price ELSE 0 END) as weekly_revenue,
                SUM(CASE WHEN appointment_date BETWEEN ? AND ? THEN total_price ELSE 0 END) as monthly_revenue,
                SUM(total_price) as total_revenue
            ", [$today, $yesterday, $startOfWeek, $endOfWeek, $startOfMonth, $endOfMonth])
            ->first();

        $todayStats = $this->getTodayAppointmentsStats($salonId, $today);

        $yesterdayStats = $this->getYesterdayAppointmentsStats($salonId, $yesterday);

        $todayRevenue = (float) ($results->today_revenue ?? 0);
        $yesterdayRevenue = (float) ($results->yesterday_revenue ?? 0);
        $weeklyRevenue = (float) ($results->weekly_revenue ?? 0);
        $monthlyRevenue = (float) ($results->monthly_revenue ?? 0);
        $totalRevenue = (float) ($results->total_revenue ?? 0);

        $revenueChange = $yesterdayRevenue > 0
            ? round((($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100, 1)
            : ($todayRevenue > 0 ? 100 : 0);

        $todayTotal = $todayStats['total'] ?? 0;
        $yesterdayTotal = $yesterdayStats['total'] ?? 0;

        $appointmentsChange = $yesterdayTotal > 0
            ? round((($todayTotal - $yesterdayTotal) / $yesterdayTotal) * 100, 1)
            : ($todayTotal > 0 ? 100 : 0);

        $completionRate = $todayTotal > 0
            ? round(($todayStats['completed'] / $todayTotal) * 100, 1)
            : 0;

        return [
            'revenue' => [
                'today' => $todayRevenue,
                'yesterday' => $yesterdayRevenue,
                'weekly' => $weeklyRevenue,
                'monthly' => $monthlyRevenue,
                'total' => $totalRevenue,
                'change_percentage' => $revenueChange,
            ],
            'today' => [
                'total' => $todayStats['total'] ?? 0,
                'completed' => $todayStats['completed'] ?? 0,
                'cancelled' => $todayStats['cancelled'] ?? 0,
                'pending' => $todayStats['pending'] ?? 0,
                'confirmed' => $todayStats['confirmed'] ?? 0,
                'completion_rate' => $completionRate,
            ],
            'yesterday' => [
                'total' => $yesterdayStats['total'] ?? 0,
                'completed' => $yesterdayStats['completed'] ?? 0,
                'cancelled' => $yesterdayStats['cancelled'] ?? 0,
                'pending' => $yesterdayStats['pending'] ?? 0,
                'confirmed' => $yesterdayStats['confirmed'] ?? 0,
            ],
            'change_percentage' => $appointmentsChange,
        ];
    }


    private function getTodayAppointmentsStats(int $salonId, string $today): array
    {
        $results = DB::table('appointments')
            ->where('salon_id', $salonId)
            ->whereDate('appointment_date', $today)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed
            ")
            ->first();

        return [
            'total' => (int) ($results->total ?? 0),
            'completed' => (int) ($results->completed ?? 0),
            'cancelled' => (int) ($results->cancelled ?? 0),
            'pending' => (int) ($results->pending ?? 0),
            'confirmed' => (int) ($results->confirmed ?? 0),
        ];
    }


    private function getYesterdayAppointmentsStats(int $salonId, string $yesterday): array
    {
        $results = DB::table('appointments')
            ->where('salon_id', $salonId)
            ->whereDate('appointment_date', $yesterday)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed
            ")
            ->first();

        return [
            'total' => (int) ($results->total ?? 0),
            'completed' => (int) ($results->completed ?? 0),
            'cancelled' => (int) ($results->cancelled ?? 0),
            'pending' => (int) ($results->pending ?? 0),
            'confirmed' => (int) ($results->confirmed ?? 0),
        ];
    }

    private function getBarbersWithStats($salon, string $today)
    {
        $barberIds = $salon->barbers()->pluck('users.id')->toArray();

        if (empty($barberIds)) {
            return collect([]);
        }

        $barberStats = DB::table('appointments')
            ->whereIn('barber_id', $barberIds)
            ->whereDate('appointment_date', $today)
            ->where('status', 'completed')
            ->selectRaw("
                barber_id,
                COUNT(*) as completed_count,
                SUM(total_price) as revenue
            ")
            ->groupBy('barber_id')
            ->get()
            ->keyBy('barber_id');

        return $salon->barbers()
            ->select('users.id', 'users.name', 'users.phone', 'users.is_active', 'users.created_at')
            ->get()
            ->map(function ($barber) use ($barberStats) {
                $stats = $barberStats->get($barber->id);

                return [
                    'id' => $barber->id,
                    'name' => $barber->name,
                    'phone' => $barber->phone,
                    'is_active' => $barber->is_active,
                    'joined_at' => $barber->created_at,
                    'today_completed' => (int) ($stats->completed_count ?? 0),
                    'today_revenue' => (float) ($stats->revenue ?? 0),
                    'avatar' => $barber->getAvatarUrlAttribute(),
                ];
            });
    }
}
