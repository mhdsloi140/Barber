<?php
// app/Http/Controllers/API/Salon/SalonServiceController.php

namespace App\Http\Controllers\API\Salon;

use App\Http\Controllers\Controller;
use App\Models\Salon;
use App\Models\BarberService;
use Illuminate\Http\Request;
use Log;

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

        // إذا لم يوجد حلاقين، أرجع مصفوفة فارغة
        if (empty($barberIds)) {
            return response()->json([
                'success' => true,
                'data' => [
                    'statistics' => [
                        'total_services' => 0,
                        'active_services' => 0,
                        'inactive_services' => 0,
                    ],
                    'services' => [],
                    'pagination' => null,
                ]
            ]);
        }

        $perPage = request()->input('per_page', 10);
        $allServices = BarberService::whereIn('barber_id', $barberIds)
            ->orderBy('name')
            ->paginate($perPage);

        // إحصائيات من قاعدة البيانات
        $totalServices = BarberService::whereIn('barber_id', $barberIds)->count();
        $activeServices = BarberService::whereIn('barber_id', $barberIds)->where('is_active', true)->count();
        $inactiveServices = BarberService::whereIn('barber_id', $barberIds)->where('is_active', false)->count();

        // تنسيق البيانات
        $services = collect($allServices->items())->map(function ($service) {
            return [
                'id' => $service->id,
                'name' => $service->name,
                'description' => $service->description,
                'price' => (float) $service->price,
                'duration_minutes' => (int) $service->duration_minutes,
                'is_active' => (bool) $service->is_active,
                'barber_id' => $service->barber_id,
                'barber_name' => $service->barber->name ?? null,
                'created_at' => $service->created_at,
            ];
        });


        $paginationData = [
            'current_page' => $allServices->currentPage(),
            'data' => $services,
            'first_page_url' => $allServices->url(1),
            'from' => $allServices->firstItem(),
            'last_page' => $allServices->lastPage(),
            'last_page_url' => $allServices->url($allServices->lastPage()),
            'next_page_url' => $allServices->nextPageUrl(),
            'path' => $allServices->path(),
            'per_page' => $allServices->perPage(),
            'prev_page_url' => $allServices->previousPageUrl(),
            'to' => $allServices->lastItem(),
            'total' => $allServices->total(),
            'has_more_pages' => $allServices->hasMorePages(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'statistics' => [
                    'total_services' => $totalServices,
                    'active_services' => $activeServices,
                    'inactive_services' => $inactiveServices,
                ],
                'services' => $paginationData,
            ]
        ]);

    } catch (\Exception $e) {
        Log::error('Get salon services error: ' . $e->getMessage());

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
