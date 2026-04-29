<?php
// app/Http/Controllers/Admin/DashboardController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Salon;
use App\Models\Appointment;
use App\Models\BarberService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * عرض لوحة التحكم الرئيسية
     */
    public function index()
    {
        // إحصائيات المستخدمين
        $usersStats = [
            'total' => User::count(),
            'active' => User::where('is_active', true)->count(),
            'inactive' => User::where('is_active', false)->count(),
            'customers' => User::role('customer')->count(),
            'barbers' => User::role('barber')->count(),
            'salon_owners' => User::role('salon_owner')->count(),
            'admins' => User::role('admin')->count(),
        ];

        // إحصائيات الصالونات
        $salonsStats = [
            'total' => Salon::count(),
            'active' => Salon::where('is_active', true)->count(),
            'inactive' => Salon::where('is_active', false)->count(),
        ];

        // إحصائيات الحجوزات
        $appointmentsStats = [
            'total' => Appointment::count(),
            'pending' => Appointment::where('status', 'pending')->count(),
            'confirmed' => Appointment::where('status', 'confirmed')->count(),
            'completed' => Appointment::where('status', 'completed')->count(),
            'cancelled' => Appointment::where('status', 'cancelled')->count(),
            'today' => Appointment::whereDate('appointment_date', Carbon::today())->count(),
            'tomorrow' => Appointment::whereDate('appointment_date', Carbon::tomorrow())->count(),
            'this_week' => Appointment::whereBetween('appointment_date', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])->count(),
            'this_month' => Appointment::whereMonth('appointment_date', Carbon::now()->month)->count(),
        ];

        // إحصائيات الإيرادات
        $revenueStats = [
            'total' => Appointment::where('status', 'completed')->sum('total_price'),
            'today' => Appointment::where('status', 'completed')->whereDate('appointment_date', Carbon::today())->sum('total_price'),
            'this_week' => Appointment::where('status', 'completed')->whereBetween('appointment_date', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])->sum('total_price'),
            'this_month' => Appointment::where('status', 'completed')->whereMonth('appointment_date', Carbon::now()->month)->sum('total_price'),
            'last_month' => Appointment::where('status', 'completed')->whereMonth('appointment_date', Carbon::now()->subMonth()->month)->sum('total_price'),
        ];

        // حساب نسبة التغير في الإيرادات
        $revenueChange = 0;
        if ($revenueStats['last_month'] > 0) {
            $revenueChange = round(($revenueStats['this_month'] - $revenueStats['last_month']) / $revenueStats['last_month'] * 100, 1);
        }

        // إحصائيات الخدمات
        $servicesStats = [
            'total' => BarberService::count(),
            'active' => BarberService::where('is_active', true)->count(),
            'inactive' => BarberService::where('is_active', false)->count(),
        ];

        // أحدث 5 حجوزات
        $recentAppointments = Appointment::with(['customer', 'barber', 'salon'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // أكثر الحلاقين طلباً
        $topBarbers = User::role('barber')
            ->withCount('barberAppointments')
            ->orderBy('barber_appointments_count', 'desc')
            ->limit(5)
            ->get();

        // أكثر الصالونات نشاطاً
        $topSalons = Salon::withCount('appointments')
            ->orderBy('appointments_count', 'desc')
            ->limit(5)
            ->get();

        return view('admin.dashboard.index', compact(
            'usersStats',
            'salonsStats',
            'appointmentsStats',
            'revenueStats',
            'revenueChange',
            'servicesStats',
            'recentAppointments',
            'topBarbers',
            'topSalons'
        ));
    }
}
