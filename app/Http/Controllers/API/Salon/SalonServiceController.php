<?php
// app/Http/Controllers/API/Salon/SalonServiceController.php

namespace App\Http\Controllers\API\Salon;

use App\Http\Controllers\Controller;
use App\Models\Salon;
use App\Models\BarberService;
use Illuminate\Http\Request;

class SalonServiceController extends Controller
{
    /**
     * عرض جميع خدمات الصالون (لصاحب الصالون)
     * GET /api/salon/services
     */
    public function index()
    {
        try {
            $salon = auth()->user()->ownedSalon;

            if (!$salon) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يوجد صالون تابع لك'
                ], 404);
            }

            // جلب جميع الحلاقين في الصالون
            $barbers = $salon->barbers()->get();
            $barberIds = $barbers->pluck('id')->toArray();

            // جلب جميع خدمات الحلاقين
            $allServices = BarberService::whereIn('barber_id', $barberIds)
                ->orderBy('name')
                ->get();

            // حساب الإحصائيات
            $totalServices = $allServices->count();
            $activeServices = $allServices->where('is_active', true)->count();
            $inactiveServices = $allServices->where('is_active', false)->count();

            // تجميع الخدمات فقط (بدون بيانات الحلاق)
            $services = $allServices->map(function ($service) {
                return [
                    'id' => $service->id,
                    'name' => $service->name,
                    'description' => $service->description,
                    'price' => $service->price,
                    'duration_minutes' => $service->duration_minutes,
                    'is_active' => $service->is_active,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'statistics' => [
                        'total_services' => $totalServices,      // إجمالي الخدمات
                        'active_services' => $activeServices,    // الخدمات النشطة
                        'inactive_services' => $inactiveServices, // الخدمات المتوقفة
                    ],
                    'services' => $services,  // قائمة الخدمات (اسم + وصف)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الخدمات',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * عرض خدمات حلاق معين في الصالون
     * GET /api/salon/barbers/{barber_id}/services
     */
    public function getBarberServices($barberId)
    {
        try {
            $salon = auth()->user()->ownedSalon;

            if (!$salon) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يوجد صالون تابع لك'
                ], 404);
            }

            // التحقق من أن الحلاق يعمل في هذا الصالون
            $barber = $salon->barbers()->where('users.id', $barberId)->first();

            if (!$barber) {
                return response()->json([
                    'success' => false,
                    'message' => 'الحلاق غير موجود في صالونك'
                ], 404);
            }

            $services = BarberService::where('barber_id', $barberId)
                ->orderBy('name')
                ->get();

            $total = $services->count();
            $active = $services->where('is_active', true)->count();
            $inactive = $services->where('is_active', false)->count();

            // تجميع الخدمات فقط
            $servicesList = $services->map(function ($service) {
                return [
                    'id' => $service->id,
                    'name' => $service->name,
                    // 'name_ar' => $service->name_ar,
                    'description' => $service->description,
                    // 'description_ar' => $service->description_ar,
                    'price' => $service->price,
                    'duration_minutes' => $service->duration_minutes,
                    'is_active' => $service->is_active,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'barber_name' => $barber->name,
                    'statistics' => [
                        'total_services' => $total,
                        'active_services' => $active,
                        'inactive_services' => $inactive,
                    ],
                    'services' => $servicesList,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الخدمات',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
