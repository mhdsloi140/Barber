<?php
// app/Services/Salon/BookingService.php

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

            // تنسيق البيانات
            $formattedAppointments = $appointments->map(function ($appointment) {
                return [
                    'id' => $appointment->id,
                    'customer_name' => $appointment->customer->name,
                    'customer_phone' => $appointment->customer->phone,
                    'barber_name' => $appointment->barber->name,
                    'barber_id' => $appointment->barber->id,
                    'service_name' => $appointment->service->name,
                    'service_price' => $appointment->service->price,
                    'date' => $appointment->appointment_date,
                    'time' => $appointment->appointment_time,
                    'end_time' => $appointment->end_time,
                    'status' => $appointment->status,
                    'created_at' => $appointment->created_at,
                ];
            });




            $response = [
                // 'statistics' => $stats,
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
