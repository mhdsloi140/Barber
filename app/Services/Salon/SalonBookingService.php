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


    private function formatDate($date): ?string
    {
        if (!$date) return null;
        if ($date instanceof Carbon) {
            return $date->format('Y-m-d');
        }
        return Carbon::parse($date)->format('Y-m-d');
    }


    private function formatTime($time): ?string
    {
        if (!$time) return null;
        if ($time instanceof Carbon) {
            return $time->format('H:i');
        }
        return Carbon::parse($time)->format('H:i');
    }


    private function calculateTotalPrice($appointment, $services): float
    {
        if ($appointment->total_price) {
            return (float) $appointment->total_price;
        }
        return (float) collect($services)->sum('price');
    }


    private function calculateTotalDuration($appointment, $services): int
    {
        if ($appointment->duration_minutes) {
            return (int) $appointment->duration_minutes;
        }
        return (int) collect($services)->sum('duration_minutes');
    }


public function getSalonAppointments(
    User $salonOwner,
    ?string $search = null,
    ?string $status = null,
    ?string $dateFrom = null,
    ?string $dateTo = null,
    int $perPage = 10
): AuthResult
{
    try {
        if (!$salonOwner->hasRole('salon_owner')) {
            return AuthResult::error('هذه الخدمة متاحة لأصحاب الصالونات فقط', null, 403);
        }

        $salon = $salonOwner->ownedSalon;

        if (!$salon) {
            return AuthResult::error('لا يوجد صالون تابع لك', null, 404);
        }

        $query = Appointment::where('salon_id', $salon->id)
            ->with(['customer', 'barber', 'service']);

        //  البحث باسم الحلاق
        if ($search && !empty(trim($search))) {
            $query->whereHas('barber', function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%');
            });
        }

        //  الفلترة حسب الحالة (pending, confirmed, completed, cancelled)
        if ($status && in_array($status, ['pending', 'confirmed', 'completed', 'cancelled'])) {
            $query->where('status', $status);
        }

        //  الفلترة حسب التاريخ (من)
        if ($dateFrom && $this->isValidDate($dateFrom)) {
            $query->whereDate('appointment_date', '>=', Carbon::parse($dateFrom)->startOfDay());
        }

        //  الفلترة حسب التاريخ (إلى)
        if ($dateTo && $this->isValidDate($dateTo)) {
            $query->whereDate('appointment_date', '<=', Carbon::parse($dateTo)->endOfDay());
        }

        // إذا تم تحديد تاريخ محدد
        if ($dateFrom && !$dateTo) {
            $query->whereDate('appointment_date', Carbon::parse($dateFrom)->toDateString());
        }

        $appointments = $query->orderBy('appointment_date', 'desc')
            ->orderBy('appointment_time', 'desc')
            ->paginate($perPage);

        $formattedAppointments = collect($appointments->items())->map(function ($appointment) {
            $services = $this->getAppointmentServices($appointment);
            $totalPrice = $this->calculateTotalPrice($appointment, $services);
            $totalDuration = $this->calculateTotalDuration($appointment, $services);
            $serviceNames = collect($services)->pluck('name')->implode(' + ');

            return [
                'id' => $appointment->id,
                'customer_name' => $appointment->customer->name ?? 'غير معروف',
                'customer_phone' => $appointment->customer->phone ?? 'غير معروف',
                'barber_name' => $appointment->barber->name ?? 'غير معروف',
                'barber_id' => $appointment->barber->id,
                'services' => $services,
                'services_summary' => $serviceNames,
                'total_price' => $totalPrice,
                'total_duration' => $totalDuration,
                'service_name' => $services[0]['name'] ?? null,
                'service_price' => $services[0]['price'] ?? null,
                'date' => $this->formatDate($appointment->appointment_date),
                'time' => $this->formatTime($appointment->appointment_time),
                'end_time' => $this->formatTime($appointment->end_time),
                'cancelled_by' => $appointment->cancelled_by ?? null,
                'day' => $appointment->appointment_date ? Carbon::parse($appointment->appointment_date)->format('l') : null,
                'status' => $appointment->status,
                'created_at' => $this->formatDateTime($appointment->created_at),
            ];
        });

        // إحصائيات الحجوزات مع مراعاة الفلتر
        $statsQuery = Appointment::where('salon_id', $salon->id);

        if ($dateFrom && $this->isValidDate($dateFrom)) {
            $statsQuery->whereDate('appointment_date', '>=', Carbon::parse($dateFrom)->startOfDay());
        }
        if ($dateTo && $this->isValidDate($dateTo)) {
            $statsQuery->whereDate('appointment_date', '<=', Carbon::parse($dateTo)->endOfDay());
        }
        if ($dateFrom && !$dateTo) {
            $statsQuery->whereDate('appointment_date', Carbon::parse($dateFrom)->toDateString());
        }

        $stats = [
            'total' => $appointments->total(),
            'pending' => (clone $statsQuery)->where('status', 'pending')->count(),
            'confirmed' => (clone $statsQuery)->where('status', 'confirmed')->count(),
            'completed' => (clone $statsQuery)->where('status', 'completed')->count(),
            'cancelled' => (clone $statsQuery)->where('status', 'cancelled')->count(),
            'today' => Appointment::where('salon_id', $salon->id)->whereDate('appointment_date', now()->toDateString())->count(),
        ];

        // إضافة معلومات الفلتر إلى الرد
        $filterInfo = [
            'search' => $search,
            'status' => $status,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];

        $paginationData = [
            'current_page' => $appointments->currentPage(),
            'data' => $formattedAppointments,
            'first_page_url' => $appointments->url(1),
            'from' => $appointments->firstItem(),
            'last_page' => $appointments->lastPage(),
            'last_page_url' => $appointments->url($appointments->lastPage()),
            'next_page_url' => $appointments->nextPageUrl(),
            'path' => $appointments->path(),
            'per_page' => $appointments->perPage(),
            'prev_page_url' => $appointments->previousPageUrl(),
            'to' => $appointments->lastItem(),
            'total' => $appointments->total(),
        ];

        $response = [
            'filters' => $filterInfo,
            'statistics' => $stats,
            'appointments' => $paginationData,
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

/**
 * التحقق من صحة التاريخ
 */
private function isValidDate(?string $date): bool
{
    if (!$date) return false;

    try {
        Carbon::parse($date);
        return true;
    } catch (\Exception $e) {
        return false;
    }
}


    private function formatDateTime($datetime): ?string
    {
        if (!$datetime) return null;
        if ($datetime instanceof Carbon) {
            return $datetime->format('Y-m-d H:i:s');
        }
        return Carbon::parse($datetime)->format('Y-m-d H:i:s');
    }
    /**
     * إلغاء حجز بواسطة صاحب الصالون (حجز واحد فقط)
     */
 public function cancelAppointment(User $salonOwner, int $appointmentId, ?string $reason = null): AuthResult
{
    try {
        return DB::transaction(function () use ($salonOwner, $appointmentId, $reason) {

            // 1. التحقق من أن المستخدم صاحب صالون
            if (!$salonOwner->hasRole('salon_owner')) {
                return AuthResult::error('هذه الخدمة متاحة لأصحاب الصالونات فقط', null, 403);
            }

            // 2. جلب الصالون الخاص به
            $salon = $salonOwner->ownedSalon;
            if (!$salon) {
                return AuthResult::error('لا يوجد صالون تابع لك', null, 404);
            }

            // 3. جلب الحجز والتأكد من أنه يتبع هذا الصالون
            $appointment = Appointment::where('id', $appointmentId)
                ->where('salon_id', $salon->id)
                ->first();

            if (!$appointment) {
                return AuthResult::error('الحجز غير موجود أو لا يتبع صالونك', null, 404);
            }

            // 4. التحقق من أن الحجز ليس ملغى بالفعل
            if ($appointment->status === 'cancelled') {
                return AuthResult::error('هذا الحجز ملغي بالفعل', null, 400);
            }

            // 5. التحقق من أن الحجز ليس مكتملاً
            if ($appointment->status === 'completed') {
                return AuthResult::error('لا يمكن إلغاء حجز مكتمل', null, 400);
            }

            // 6. إلغاء الحجز (فقط تغيير الحالة)
            $appointment->status = 'cancelled';
            $appointment->cancelled_by = 'salon_owner';
            $appointment->save();

            Log::info('Appointment cancelled', [
                'appointment_id' => $appointmentId,
                'salon_id' => $salon->id,
            ]);

            return AuthResult::success('تم إلغاء الحجز بنجاح', [
                'id' => $appointment->id,
                'status' => $appointment->status,
            ]);

        });
    } catch (\Exception $e) {
        Log::error('Cancel appointment error: ' . $e->getMessage());
        return AuthResult::error('حدث خطأ أثناء إلغاء الحجز', null, 500);
    }
}

}
