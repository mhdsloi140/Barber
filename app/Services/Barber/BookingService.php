<?php
// app/Services/Barber/BookingService.php

namespace App\Services\Barber;

use App\Models\User;
use App\Models\Appointment;
use App\Models\BarberService;
use App\Services\AuthResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class BookingService
{
    /**
     * جلب جميع حجوزات الحلاق (قيد الانتظار فقط)
     */
    public function getPendingAppointments(User $barber): AuthResult
    {
        try {
            if (!$barber->hasRole('barber')) {
                return AuthResult::error('هذه الخدمة متاحة للحلاقين فقط', null, 403);
            }

            $appointments = Appointment::where('barber_id', $barber->id)
                ->where('status', 'pending')
                ->with(['customer', 'salon', 'service'])
                ->orderBy('appointment_date', 'asc')
                ->orderBy('appointment_time', 'asc')
                ->get();

            $formattedAppointments = $appointments->map(function ($appointment) {
                return [
                    'id' => $appointment->id,
                    'customer_name' => $appointment->customer->name,
                    'customer_phone' => $appointment->customer->phone,
                    'services' => $this->getAppointmentServices($appointment), //  جميع الخدمات
                    'total_price' => $appointment->total_price,
                    'duration_minutes' => $appointment->duration_minutes,
                    'date' => $appointment->appointment_date,
                    'time' => $appointment->appointment_time,
                    'end_time' => $appointment->end_time,
                    'status' => $appointment->status,
                ];
            });

            return AuthResult::success('تم جلب الحجوزات قيد الانتظار بنجاح', $formattedAppointments);

        } catch (\Exception $e) {
            Log::error('Get pending appointments error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب الحجوزات', $e->getMessage(), 500);
        }
    }

    /**
     * تأكيد حجز (موافقة)
     */
    public function approveAppointment(User $barber, int $appointmentId): AuthResult
    {
        try {
            return DB::transaction(function () use ($barber, $appointmentId) {

                $appointment = Appointment::where('barber_id', $barber->id)
                    ->where('id', $appointmentId)
                    ->first();

                if (!$appointment) {
                    return AuthResult::error('الحجز غير موجود', null, 404);
                }

                if ($appointment->status !== 'pending') {
                    return AuthResult::error('لا يمكن تأكيد هذا الحجز، حالته الحالية: ' . $appointment->status, null, 400);
                }

                $appointment->status = 'confirmed';
                $appointment->save();

                return AuthResult::success('تم تأكيد الحجز بنجاح', [
                    'id' => $appointment->id,
                    'status' => $appointment->status,
                    'customer_name' => $appointment->customer->name,
                    'date' => $appointment->appointment_date,
                    'time' => $appointment->appointment_time,
                    'services' => $this->getAppointmentServices($appointment), //  جميع الخدمات
                    'total_price' => $appointment->total_price,
                    'duration_minutes' => $appointment->duration_minutes,
                    'end_time' => $appointment->end_time,
                ]);

            });
        } catch (\Exception $e) {
            Log::error('Approve appointment error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء تأكيد الحجز', $e->getMessage(), 500);
        }
    }

    /**
     * رفض حجز
     */
    public function rejectAppointment(User $barber, int $appointmentId, ?string $reason = null): AuthResult
    {
        try {
            return DB::transaction(function () use ($barber, $appointmentId, $reason) {

                $appointment = Appointment::where('barber_id', $barber->id)
                    ->where('id', $appointmentId)
                    ->first();

                if (!$appointment) {
                    return AuthResult::error('الحجز غير موجود', null, 404);
                }

                if ($appointment->status !== 'pending') {
                    return AuthResult::error('لا يمكن رفض هذا الحجز، حالته الحالية: ' . $appointment->status, null, 400);
                }

                $appointment->status = 'cancelled';
                $appointment->save();

                return AuthResult::success('تم رفض الحجز بنجاح', [
                    'id' => $appointment->id,
                    'status' => $appointment->status,
                    'customer_name' => $appointment->customer->name,
                    'date' => $appointment->appointment_date,
                    'time' => $appointment->appointment_time,
                    'services' => $this->getAppointmentServices($appointment), //  جميع الخدمات
                    'total_price' => $appointment->total_price,
                    'duration_minutes' => $appointment->duration_minutes,
                    'end_time' => $appointment->end_time,
                ]);

            });
        } catch (\Exception $e) {
            Log::error('Reject appointment error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء رفض الحجز: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * جلب جميع حجوزات الحلاق
     */
    public function getBarberAppointments(User $barber): AuthResult
    {
        try {
            if (!$barber->hasRole('barber')) {
                return AuthResult::error('هذه الخدمة متاحة للحلاقين فقط', null, 403);
            }

            $appointments = Appointment::where('barber_id', $barber->id)
                ->with(['customer', 'salon', 'service'])
                ->orderBy('appointment_date', 'desc')
                ->orderBy('appointment_time', 'desc')
                ->get();

            $formattedAppointments = $appointments->map(function ($appointment) {
                return [
                    'id' => $appointment->id,
                    'customer_name' => $appointment->customer->name,
                    'customer_phone' => $appointment->customer->phone,
                    'services' => $this->getAppointmentServices($appointment), //  جميع الخدمات
                    'total_price' => $appointment->total_price,
                    'duration_minutes' => $appointment->duration_minutes,
                    'date' => $appointment->appointment_date,
                    'time' => $appointment->appointment_time,
                    'end_time' => $appointment->end_time,
                    'status' => $appointment->status,
                ];
            });

            return AuthResult::success('تم جلب الحجوزات بنجاح', $formattedAppointments);

        } catch (\Exception $e) {
            Log::error('Get barber appointments error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب الحجوزات', $e->getMessage(), 500);
        }
    }

    /**
     *  جلب جميع الخدمات المرتبطة بالحجز
     */
    private function getAppointmentServices(Appointment $appointment): array
    {
        // إذا كان هناك عمود services_details يحتوي على تفاصيل الخدمات
        if ($appointment->services_details) {
            return json_decode($appointment->services_details, true);
        }

        // إذا كان هناك عمود services يحتوي على مصفوفة IDs
        if ($appointment->services) {
            $serviceIds = json_decode($appointment->services, true);
            if (is_array($serviceIds) && !empty($serviceIds)) {
                $services = BarberService::whereIn('id', $serviceIds)->get();
                return $services->map(function ($service) {
                    return [
                        'id' => $service->id,
                        'name' => $service->name,
                        'price' => $service->price,
                        'duration_minutes' => $service->duration_minutes,
                    ];
                })->toArray();
            }
        }

        // إذا كانت خدمة واحدة فقط (للتوافق مع الإصدارات السابقة)
        if ($appointment->service) {
            return [
                [
                    'id' => $appointment->service->id,
                    'name' => $appointment->service->name,
                    'price' => $appointment->service->price,
                    'duration_minutes' => $appointment->service->duration_minutes,
                ]
            ];
        }

        return [];
    }
}
