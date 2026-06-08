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
use App\Services\Notification\FirebaseNotificationService;

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

            return AuthResult::success('تم جلب الحجوزات قيد الانتظار بنجاح', $response);

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
                try {
                    $notificationService = app(FirebaseNotificationService::class);
                    $notificationService->notifyAppointmentApprovedToCustomer($appointment);
                } catch (\Exception $e) {
                    Log::error('Failed to send approval notification: ' . $e->getMessage());
                }

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
                // $appointment->cancellation_reason = $reason ?? 'تم الرفض من قبل الحلاق';
                $appointment->save();
                try {
                    $notificationService = app(FirebaseNotificationService::class);
                    $notificationService->notifyAppointmentRejectedToCustomer($appointment, $reason);
                } catch (\Exception $e) {
                    Log::error('Failed to send rejection notification: ' . $e->getMessage());
                }

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
public function getBarberAppointments(User $barber, ?string $date = null, int $perPage = 10): AuthResult
{
    try {
        if (!$barber->hasRole('barber')) {
            return AuthResult::error('هذه الخدمة متاحة للحلاقين فقط', null, 403);
        }

        // تحديد التاريخ المستهدف
        $targetDate = $date ? Carbon::parse($date)->format('Y-m-d') : Carbon::today()->format('Y-m-d');

        // بناء الاستعلام
        $query = Appointment::where('barber_id', $barber->id)
            ->with(['customer', 'salon']);


        if ($date) {
            $query->whereDate('appointment_date', $targetDate);

        } else {

            $query->whereIn('status', ['pending', 'confirmed'])
                ->whereDate('appointment_date', '>=', $targetDate);
        }

        $appointments = $query->orderBy('appointment_date', 'asc')
            ->orderBy('appointment_time', 'asc')
            ->paginate($perPage);

        $formattedAppointments = collect($appointments->items())->map(function ($appointment) {
            return $this->formatAppointment($appointment);
        });


        $statsQuery = Appointment::where('barber_id', $barber->id);

        if ($date) {

            $statsQuery->whereDate('appointment_date', $targetDate);
        } else {
           
            $statsQuery->whereIn('status', ['pending', 'confirmed'])
                ->whereDate('appointment_date', '>=', $targetDate);
        }

        $statsFromDatabase = [
            'total' => (clone $statsQuery)->count(),
            'pending' => (clone $statsQuery)->where('status', 'pending')->count(),
            'confirmed' => (clone $statsQuery)->where('status', 'confirmed')->count(),
            'completed' => (clone $statsQuery)->where('status', 'completed')->count(),
            'cancelled' => (clone $statsQuery)->where('status', 'cancelled')->count(),
            'today' => Appointment::where('barber_id', $barber->id)->whereDate('appointment_date', now()->toDateString())->count(),
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
            'date' => $targetDate,
            'is_filtered_by_date' => !is_null($date),
            'statistics' => $statsFromDatabase,
            'appointments' => $paginationData,
        ];

        return AuthResult::success('تم جلب الحجوزات بنجاح', $response);

    } catch (\Exception $e) {
        Log::error('Get barber appointments error: ' . $e->getMessage());
        return AuthResult::error('حدث خطأ أثناء جلب الحجوزات', $e->getMessage(), 500);
    }
}
    /**
     * إلغاء حجز بواسطة الحلاق
     */
    public function cancelAppointment(User $barber, int $appointmentId, ?string $reason = null): AuthResult
    {
        try {
            return DB::transaction(function () use ($barber, $appointmentId, $reason) {

                $appointment = Appointment::where('barber_id', $barber->id)
                    ->where('id', $appointmentId)
                    ->first();

                if (!$appointment) {
                    return AuthResult::error('الحجز غير موجود', null, 404);
                }

                if (!in_array($appointment->status, ['pending', 'confirmed'])) {
                    return AuthResult::error("لا يمكن إلغاء هذا الحجز، حالته الحالية: {$appointment->status}", null, 400);
                }

                // حفظ الحالة القديمة للتسجيل
                $oldStatus = $appointment->status;

                // تحديث الحجز
                $appointment->status = 'cancelled';
                $appointment->cancelled_by = 'barber';
                $appointment->save();


                try {
                    $notificationService = app(FirebaseNotificationService::class);
                    $notificationService->notifyAppointmentRejectedToCustomer($appointment, $reason);

                } catch (\Exception $e) {
                    Log::error('Failed to send cancellation notification to customer: ' . $e->getMessage());
                }


                try {
                    $salon = $appointment->salon;
                    if ($salon && $salon->owner) {
                        $notificationService = app(FirebaseNotificationService::class);
                        $notificationService->notifySalonOwnerAboutCancelledAppointment($appointment, $reason);
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to send cancellation notification to salon owner: ' . $e->getMessage());
                }

                return AuthResult::success('تم إلغاء الحجز بنجاح', [
                    'id' => $appointment->id,
                    'status' => $appointment->status,
                    'cancelled_by' => $appointment->cancelled_by


                ]);
            });
        } catch (\Exception $e) {
            Log::error('Barber cancel appointment error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء إلغاء الحجز: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * جلب جميع حجوزات الحلاق مع البحث
     */
    public function getBarberAppointmentsWithSearch(User $barber, ?string $search = null): AuthResult
    {
        try {
            if (!$barber->hasRole('barber')) {
                return AuthResult::error('هذه الخدمة متاحة للحلاقين فقط', null, 403);
            }

            $query = Appointment::where('barber_id', $barber->id)
                ->with(['customer', 'salon']);

            if ($search && !empty(trim($search))) {
                $query->whereHas('customer', function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                        ->orWhere('phone', 'like', '%' . $search . '%');
                });
            }

            $appointments = $query->orderBy('appointment_date', 'desc')
                ->orderBy('appointment_time', 'desc')
                ->get();

            $formattedAppointments = $appointments->map(function ($appointment) {
                return $this->formatAppointment($appointment);
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

            return AuthResult::success('تم جلب الحجوزات بنجاح', $response);

        } catch (\Exception $e) {
            Log::error('Get barber appointments with search error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب الحجوزات', null, 500);
        }
    }

    /**
     * تنسيق بيانات الحجز (نفس تنسيق SalonBookingService)
     */
    private function formatAppointment(Appointment $appointment): array
    {
        $services = $this->getAppointmentServices($appointment);
        $totalPrice = $this->calculateTotalPrice($appointment, $services);
        $totalDuration = $this->calculateTotalDuration($appointment, $services);
        $serviceNames = collect($services)->pluck('name')->implode(' + ');

        return [
            'id' => $appointment->id,
            'customer_name' => $appointment->customer->name ?? 'غير معروف',
            'customer_phone' => $appointment->customer->phone ?? 'غير معروف',
            'barber_name' => $appointment->barber->name ?? ($appointment->barber_id ? 'حلاق' : 'غير محدد'),
            'barber_id' => $appointment->barber_id,

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
            'cancelled_by' => $appointment->cancelled_by ?? null,
            'status' => $appointment->status,
            'created_at' => $this->formatDateTime($appointment->created_at),
        ];
    }

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
                return array_map(function ($service) {
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

    /**
     * حساب السعر الإجمالي
     */
    private function calculateTotalPrice($appointment, $services): float
    {
        if ($appointment->total_price) {
            return (float) $appointment->total_price;
        }
        return (float) collect($services)->sum('price');
    }

    /**
     * حساب المدة الإجمالية
     */
    private function calculateTotalDuration($appointment, $services): int
    {
        if ($appointment->duration_minutes) {
            return (int) $appointment->duration_minutes;
        }
        return (int) collect($services)->sum('duration_minutes');
    }

    /**
     * تنسيق التاريخ
     */
    private function formatDate($date): ?string
    {
        if (!$date)
            return null;
        if ($date instanceof Carbon) {
            return $date->format('Y-m-d');
        }
        return Carbon::parse($date)->format('Y-m-d');
    }

    /**
     * تنسيق الوقت
     */
    private function formatTime($time): ?string
    {
        if (!$time)
            return null;
        if ($time instanceof Carbon) {
            return $time->format('H:i');
        }
        return Carbon::parse($time)->format('H:i');
    }

    /**
     * تنسيق التاريخ والوقت
     */
    private function formatDateTime($datetime): ?string
    {
        if (!$datetime)
            return null;
        if ($datetime instanceof Carbon) {
            return $datetime->format('Y-m-d H:i:s');
        }
        return Carbon::parse($datetime)->format('Y-m-d H:i:s');
    }
}
