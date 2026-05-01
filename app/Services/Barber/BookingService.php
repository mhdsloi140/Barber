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
                ->with(['customer', 'salon'])
                ->orderBy('appointment_date', 'asc')
                ->orderBy('appointment_time', 'asc')
                ->get();

            $formattedAppointments = $appointments->map(function ($appointment) {
                return $this->formatAppointment($appointment);
            });

            return AuthResult::success('تم جلب الحجوزات قيد الانتظار بنجاح', $formattedAppointments);

        } catch (\Exception $e) {
            Log::error('Get pending appointments error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب الحجوزات', null, 500);
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

                return AuthResult::success('تم تأكيد الحجز بنجاح', $this->formatAppointment($appointment));

            });
        } catch (\Exception $e) {
            Log::error('Approve appointment error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء تأكيد الحجز', null, 500);
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
                $appointment->cancellation_reason = $reason ?? 'تم الرفض من قبل الحلاق';
                $appointment->save();

                return AuthResult::success('تم رفض الحجز بنجاح', $this->formatAppointment($appointment));

            });
        } catch (\Exception $e) {
            Log::error('Reject appointment error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء رفض الحجز', null, 500);
        }
    }

    /**
     * جلب جميع حجوزات الحلاق مع الإحصائيات
     */
    public function getBarberAppointments(User $barber): AuthResult
    {
        try {
            if (!$barber->hasRole('barber')) {
                return AuthResult::error('هذه الخدمة متاحة للحلاقين فقط', null, 403);
            }

            $today = now()->toDateString();
            $now = now()->format('H:i:s');

            $appointments = Appointment::where('barber_id', $barber->id)
                ->with(['customer', 'salon'])
                ->orderBy('appointment_date', 'asc')
                ->orderBy('appointment_time', 'asc')
                ->get();

            $upcomingAppointments = [];
            $todayAppointments = [];
            $completedAppointments = [];
            $cancelledAppointments = [];
            $nextAppointment = null;

            foreach ($appointments as $appointment) {
                $formatted = $this->formatAppointment($appointment);

                if ($appointment->status === 'cancelled') {
                    $cancelledAppointments[] = $formatted;
                } elseif ($appointment->status === 'completed') {
                    $completedAppointments[] = $formatted;
                } elseif ($appointment->appointment_date > $today) {
                    $upcomingAppointments[] = $formatted;
                } elseif ($appointment->appointment_date === $today && $appointment->appointment_time >= $now) {
                    $todayAppointments[] = $formatted;
                    if (!$nextAppointment) {
                        $nextAppointment = $formatted;
                    }
                } elseif ($appointment->appointment_date === $today && $appointment->appointment_time < $now) {
                    $completedAppointments[] = $formatted;
                } else {
                    $upcomingAppointments[] = $formatted;
                }
            }

            $upcomingAppointments = collect($upcomingAppointments)
                ->sortBy([['date', 'asc'], ['time', 'asc']])
                ->values()
                ->take(10)
                ->toArray();

            $todayAppointments = collect($todayAppointments)
                ->sortBy('time')
                ->values()
                ->toArray();

            $statistics = [
                'upcoming_count' => count($upcomingAppointments),
                'completed_count' => count($completedAppointments),
                'today_count' => count($todayAppointments),
                'cancelled_count' => count($cancelledAppointments),
                'total_today' => $appointments->where('appointment_date', $today)->count(),
            ];

            $nextCustomer = $nextAppointment ? [
                'customer_name' => $nextAppointment['customer_name'],
                'service_names' => $nextAppointment['services'],
                'duration_minutes' => $nextAppointment['duration_minutes'],
                'time' => $nextAppointment['time'],
                'total_price' => $nextAppointment['total_price'],
            ] : null;

            return AuthResult::success('تم جلب الحجوزات بنجاح', [
                'statistics' => $statistics,
                'next_customer' => $nextCustomer,
                'today_appointments' => $todayAppointments,
                'upcoming_appointments' => $upcomingAppointments,
                'completed_appointments' => $completedAppointments,
                'cancelled_appointments' => $cancelledAppointments,
            ]);

        } catch (\Exception $e) {
            Log::error('Get barber appointments error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب الحجوزات', null, 500);
        }
    }

    /**
     * تنسيق بيانات الحجز
     */
    private function formatAppointment(Appointment $appointment): array
    {
        $services = $this->getAppointmentServices($appointment);
        $serviceNames = array_column($services, 'name');

        return [
            'id' => $appointment->id,
            'customer_name' => $appointment->customer->name ?? 'غير معروف',
            'customer_phone' => $appointment->customer->phone ?? 'غير معروف',
            'services' => $serviceNames,
            'services_count' => count($serviceNames),
            'services_details' => $services,
            'total_price' => (float) $appointment->total_price,
            'duration_minutes' => (int) $appointment->duration_minutes,

            //  التاريخ والوقت بصيغة نظيفة
            'date' => $appointment->appointment_date
                ? Carbon::parse($appointment->appointment_date)->format('Y-m-d')
                : null,
            'time' => $appointment->appointment_time
                ? Carbon::parse($appointment->appointment_time)->format('H:i:s')
                : null,

            // التاريخ المنسق بالعربية للعرض
            'date_formatted' => $appointment->appointment_date
                ? $this->formatDate($appointment->appointment_date)
                : null,

            'status' => $appointment->status,
            'status_text' => $this->getStatusText($appointment->status),
        ];
    }

    /**
     * جلب جميع الخدمات المرتبطة بالحجز
     */
    private function getAppointmentServices(Appointment $appointment): array
    {
        // إذا كان هناك علاقة many-to-many مع الخدمات
        if (method_exists($appointment, 'services') && $appointment->relationLoaded('services')) {
            return $appointment->services->map(function ($service) {
                return [
                    'id' => $service->id,
                    'name' => $service->name,
                    'price' => (float) $service->price,
                    'duration_minutes' => (int) $service->duration_minutes,
                ];
            })->toArray();
        }

        // إذا كان هناك عمود services_details (JSON)
        if ($appointment->services_details) {
            return json_decode($appointment->services_details, true);
        }

        // إذا كان هناك عمود services (JSON array of IDs)
        if ($appointment->services) {
            $serviceIds = json_decode($appointment->services, true);
            if (is_array($serviceIds) && !empty($serviceIds)) {
                $services = BarberService::whereIn('id', $serviceIds)->get();
                return $services->map(function ($service) {
                    return [
                        'id' => $service->id,
                        'name' => $service->name,
                        'price' => (float) $service->price,
                        'duration_minutes' => (int) $service->duration_minutes,
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
                    'price' => (float) $appointment->service->price,
                    'duration_minutes' => (int) $appointment->service->duration_minutes,
                ]
            ];
        }

        return [];
    }

    /**
     * تنسيق التاريخ باللغة العربية
     */
    private function formatDate(string $date): string
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

        $months = [
            'January' => 'يناير',
            'February' => 'فبراير',
            'March' => 'مارس',
            'April' => 'أبريل',
            'May' => 'مايو',
            'June' => 'يونيو',
            'July' => 'يوليو',
            'August' => 'أغسطس',
            'September' => 'سبتمبر',
            'October' => 'أكتوبر',
            'November' => 'نوفمبر',
            'December' => 'ديسمبر',
        ];

        $timestamp = strtotime($date);
        $day = date('l', $timestamp);
        $dayNumber = date('d', $timestamp);
        $month = date('F', $timestamp);
        $year = date('Y', $timestamp);

        return $days[$day] . '، ' . $dayNumber . ' ' . $months[$month] . ' ' . $year;
    }

    /**
     * الحصول على النص العربي للحالة
     */
    private function getStatusText(string $status): string
    {
        return match ($status) {
            'pending' => 'قيد الانتظار',
            'confirmed' => 'مؤكد',
            'completed' => 'مكتمل',
            'cancelled' => 'ملغي',
            default => $status,
        };
    }
}
