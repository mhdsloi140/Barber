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


    public function getSalonAppointments(User $salonOwner, ?string $search = null): AuthResult
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

            if ($search && !empty(trim($search))) {
                $query->whereHas('barber', function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%');
                });
            }

            $appointments = $query->orderBy('appointment_date', 'desc')
                ->orderBy('appointment_time', 'desc')
                ->get();


            $formattedAppointments = $appointments->map(function ($appointment) {
                $services = $this->getAppointmentServices($appointment);
                $totalPrice = $this->calculateTotalPrice($appointment, $services);
                $totalDuration = $this->calculateTotalDuration($appointment, $services);
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


                    'date' => $this->formatDate($appointment->appointment_date),
                    'time' => $this->formatTime($appointment->appointment_time),
                    'end_time' => $this->formatTime($appointment->end_time),
                    'day' => $appointment->appointment_date ? Carbon::parse($appointment->appointment_date)->format('l') : null,

                    'status' => $appointment->status,
                    'created_at' => $this->formatDateTime($appointment->created_at),
                ];
            });

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


    private function formatDateTime($datetime): ?string
    {
        if (!$datetime) return null;
        if ($datetime instanceof Carbon) {
            return $datetime->format('Y-m-d H:i:s');
        }
        return Carbon::parse($datetime)->format('Y-m-d H:i:s');
    }
}
