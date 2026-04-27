<?php
// app/Services/Salon/SalonBookingService.php

namespace App\Services\Salon;

use App\Models\User;
use App\Models\Appointment;
use App\Models\BarberService;
use App\Services\AuthResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SalonBookingService
{
    /**
     * جلب جميع الخدمات المرتبطة بالحجز
     */
    private function getAppointmentServices(Appointment $appointment): array
    {

        if ($appointment->services_details) {
            if (is_array($appointment->services_details)) {
                $services = $appointment->services_details;
            } else {
                $services = json_decode($appointment->services_details, true);
            }

            if (is_array($services) && !empty($services)) {
                return array_map(function($service) {
                    return [
                        'id' => $service['id'] ?? null,
                        'name' => $service['name'] ?? null,
                        'price' => $service['price'] ?? 0,
                        'duration_minutes' => $service['duration_minutes'] ?? 0,
                    ];
                }, $services);
            }
        }


        if ($appointment->services) {

            if (is_array($appointment->services)) {
                $serviceIds = $appointment->services;
            } else {
                $serviceIds = json_decode($appointment->services, true);
            }

            if (is_array($serviceIds) && !empty($serviceIds)) {
                $services = BarberService::whereIn('id', $serviceIds)->get();
                return $services->map(function($service) {
                    return [
                        'id' => $service->id,
                        'name' => $service->name,
                        'price' => $service->price,
                        'duration_minutes' => $service->duration_minutes,
                    ];
                })->toArray();
            }
        }

        // خدمة واحدة فقط (للتوافق مع الإصدارات السابقة)
        if ($appointment->service) {
            return [[
                'id' => $appointment->service->id,
                'name' => $appointment->service->name,
                'price' => $appointment->service->price,
                'duration_minutes' => $appointment->service->duration_minutes,
            ]];
        }

        return [];
    }

    /**
     * جلب جميع حجوزات الصالون (لصاحب الصالون)
     * مع إمكانية البحث عن حلاق بالاسم (اختياري)
     */
    public function getSalonAppointments(User $salonOwner, ?string $search = null): AuthResult
    {
        try {
            // التحقق من أن المستخدم صاحب صالون
            if (!$salonOwner->hasRole('salon_owner')) {
                return AuthResult::error('هذه الخدمة متاحة لأصحاب الصالونات فقط', null, 403);
            }

            // جلب الصالون الخاص به
            $salon = $salonOwner->ownedSalon;

            if (!$salon) {
                return AuthResult::error('لا يوجد صالون تابع لك', null, 404);
            }

            $query = Appointment::where('salon_id', $salon->id)
                ->with(['customer', 'barber', 'service']);

            if ($search && !empty(trim($search))) {
                $query->whereHas('barber', function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%');
                });
            }

            $appointments = $query->orderBy('appointment_date', 'desc')
                ->orderBy('appointment_time', 'desc')
                ->get();

            // تنسيق البيانات مع دعم خدمات متعددة
            $formattedAppointments = $appointments->map(function ($appointment) {
                // جلب جميع الخدمات
                $services = $this->getAppointmentServices($appointment);

                // حساب إجمالي السعر والمدة
                $totalPrice = $appointment->total_price ?? collect($services)->sum('price');
                $totalDuration = $appointment->duration_minutes ?? collect($services)->sum('duration_minutes');

                // أسماء الخدمات (للعرض السريع)
                $serviceNames = collect($services)->pluck('name')->implode(' + ');

                return [
                    'id' => $appointment->id,
                    'customer_name' => $appointment->customer->name,
                    'customer_phone' => $appointment->customer->phone,
                    'barber_name' => $appointment->barber->name,
                    'barber_id' => $appointment->barber->id,

                    'services' => $services,
                    'services_summary' => $serviceNames,
                    'total_price' => $totalPrice,
                    'total_duration' => $totalDuration,

                    'service_name' => $services[0]['name'] ?? null,
                    'service_price' => $services[0]['price'] ?? null,
                    'date' => $appointment->appointment_date,
                    'time' => $appointment->appointment_time,
                    'end_time' => $appointment->end_time,
                    'status' => $appointment->status,
                    'created_at' => $appointment->created_at,
                ];
            });

            // إحصائيات الحجوزات
            $stats = [
                'total' => $appointments->count(),
                'pending' => $appointments->where('status', 'pending')->count(),
                'confirmed' => $appointments->where('status', 'confirmed')->count(),
                'completed' => $appointments->where('status', 'completed')->count(),
                'cancelled' => $appointments->where('status', 'cancelled')->count(),
                'today' => $appointments->where('appointment_date', now()->toDateString())->count(),
            ];

            $response = [
                'statistics' => $stats,
                'appointments' => $formattedAppointments,
            ];

            if ($search && !empty(trim($search))) {
                $barber = User::role('barber')
                    ->whereHas('salons', function($q) use ($salon) {
                        $q->where('salon_id', $salon->id);
                    })
                    ->where('name', 'like', '%' . $search . '%')
                    ->first();

                if ($barber) {
                    $response['searched_barber'] = [
                        'id' => $barber->id,
                        'name' => $barber->name,
                        'phone' => $barber->phone,
                    ];
                }
            }

            return AuthResult::success('تم جلب حجوزات الصالون بنجاح', $response);

        } catch (\Exception $e) {
            Log::error('Get salon appointments error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب الحجوزات', $e->getMessage(), 500);
        }
    }
}
