<?php
// app/Services/Customer/BookingService.php

namespace App\Services\Customer;

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
     * تحويل اليوم إلى أقرب تاريخ مستقبلي
     */
    private function getNextDateFromDay(string $day): string
    {
        $daysMap = [
            'sunday' => 0,
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
        ];

        $today = Carbon::today();
        $targetDay = $daysMap[$day];
        $currentDay = $today->dayOfWeek;

        if ($targetDay > $currentDay) {
            $date = $today->copy()->addDays($targetDay - $currentDay);
        } elseif ($targetDay < $currentDay) {
            $date = $today->copy()->addDays(7 - ($currentDay - $targetDay));
        } else {
            $date = $today->copy()->addDays(7);
        }

        return $date->format('Y-m-d');
    }

    /**
     * حساب إجمالي السعر والمدة من الخدمات
     */
    private function calculateTotals($services): array
    {
        $totalPrice = 0;
        $totalDuration = 0;
        $serviceNames = [];

        foreach ($services as $service) {
            $totalPrice += $service->price;
            $totalDuration += $service->duration_minutes;
            $serviceNames[] = $service->name;
        }

        return [
            'total_price' => $totalPrice,
            'total_duration' => $totalDuration,
            'services_names' => implode(' + ', $serviceNames),
        ];
    }

    /**
     * حفظ الحجز الجديد (مع دعم خدمات متعددة)
     */
    public function storeBooking(array $data): AuthResult
    {
        try {
            return DB::transaction(function () use ($data) {

                $customer = auth()->user();

                if (!$customer) {
                    return AuthResult::error('يجب تسجيل الدخول أولاً', null, 401);
                }

                if (!$customer->hasRole('customer')) {
                    return AuthResult::error('هذه الخدمة متاحة للزبائن فقط', null, 403);
                }

                $barber = User::where('is_active', true)
                    ->where('id', $data['barber_id'])
                    ->whereHas('salons', function ($q) use ($data) {
                        $q->where('salon_id', $data['salon_id']);
                    })
                    ->first();

                if (!$barber) {
                    return AuthResult::error('الحلاق غير موجود أو لا يعمل في هذا الصالون', null, 404);
                }

                if (!$barber->hasRole('barber')) {
                    return AuthResult::error('المستخدم المحدد ليس حلاقاً', null, 400);
                }

                $services = BarberService::whereIn('id', $data['service_ids'])
                    ->where('barber_id', $data['barber_id'])
                    ->where('is_active', true)
                    ->get();

                if ($services->count() !== count($data['service_ids'])) {
                    return AuthResult::error('بعض الخدمات غير متاحة لهذا الحلاق', null, 404);
                }

                $totals = $this->calculateTotals($services);
                $totalDuration = $totals['total_duration'];
                $totalPrice = $totals['total_price'];

                $appointmentDate = $this->getNextDateFromDay($data['day']);
                $appointmentTime = $data['time'];

                $endTime = Carbon::parse($appointmentTime)
                    ->addMinutes($totalDuration)
                    ->format('H:i:s');

                $workingHour = $barber->workingHours()
                    ->where('day_of_week', $data['day'])
                    ->where('is_open', true)
                    ->first();

                if (!$workingHour) {
                    return AuthResult::error('الحلاق لا يعمل في هذا اليوم', null, 400);
                }

                $isValidTime = false;

                if ($workingHour->shift1_start && $workingHour->shift1_end) {
                    if (
                        $appointmentTime >= $workingHour->shift1_start &&
                        $endTime <= $workingHour->shift1_end
                    ) {
                        $isValidTime = true;
                    }
                }

                if (!$isValidTime && $workingHour->shift2_start && $workingHour->shift2_end) {
                    if (
                        $appointmentTime >= $workingHour->shift2_start &&
                        $endTime <= $workingHour->shift2_end
                    ) {
                        $isValidTime = true;
                    }
                }

                if (!$isValidTime) {
                    return AuthResult::error('الوقت المحدد خارج ساعات عمل الحلاق', null, 400);
                }

                $conflictingBooking = Appointment::where('barber_id', $data['barber_id'])
                    ->whereDate('appointment_date', $appointmentDate)
                    ->whereIn('status', ['pending', 'confirmed'])
                    ->where(function ($query) use ($appointmentTime, $endTime) {
                        $query->where(function ($q) use ($appointmentTime, $endTime) {
                            $q->where('appointment_time', '<', $endTime)
                              ->where('end_time', '>', $appointmentTime);
                        });
                    })
                    ->exists();

                if ($conflictingBooking) {
                    $availableTimes = $this->getAvailableTimesForBarber(
                        $barber->id,
                        $appointmentDate,
                        $totalDuration,
                        $workingHour
                    );

                    return AuthResult::errorWithData(
                        'هذا الوقت غير متاح، يرجى اختيار وقت آخر',
                        ['available_times' => $availableTimes],
                        400
                    );
                }

                $appointment = Appointment::create([
                    'customer_id' => $customer->id,
                    'barber_id' => $data['barber_id'],
                    'salon_id' => $data['salon_id'],
                    'service_id' => $data['service_ids'][0],
                    'services' => json_encode($data['service_ids']),
                    'services_details' => json_encode($services->toArray()),
                    'appointment_date' => $appointmentDate,
                    'appointment_time' => $appointmentTime,
                    'end_time' => $endTime,
                    'status' => 'pending',
                    'total_price' => $totalPrice,
                    'duration_minutes' => $totalDuration,
                    'notes' => $data['notes'] ?? null,
                ]);

                $appointment->load(['customer', 'barber', 'salon']);

                return AuthResult::success('تم إنشاء الحجز بنجاح', [
                    'appointment' => [
                        'id' => $appointment->id,
                        'customer' => [
                            'id' => $appointment->customer->id,
                            'name' => $appointment->customer->name,
                            'phone' => $appointment->customer->phone,
                        ],
                        'barber' => [
                            'id' => $appointment->barber->id,
                            'name' => $appointment->barber->name,
                        ],
                        'salon' => [
                            'id' => $appointment->salon->id,
                            'name' => $appointment->salon->name,
                        ],
                        'services' => $services->map(function($service) {
                            return [
                                'id' => $service->id,
                                'name' => $service->name,
                                'price' => $service->price,
                                'duration_minutes' => $service->duration_minutes,
                            ];
                        }),
                        'services_summary' => $totals['services_names'],
                        'total_duration' => $totalDuration,
                        'total_price' => $totalPrice,
                        'date' => $appointment->appointment_date,
                        'day' => $data['day'],
                        'time' => $appointment->appointment_time,
                        'end_time' => $appointment->end_time,
                        'status' => $appointment->status,
                      //  'notes' => $appointment->notes,
                        'created_at' => $appointment->created_at,
                    ],
                ], 201);

            });
        } catch (\Exception $e) {
            return AuthResult::error('حدث خطأ أثناء إنشاء الحجز: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * جلب الأوقات المتاحة للحلاق في يوم معين
     */
    private function getAvailableTimesForBarber(int $barberId, string $date, int $duration, $workingHour): array
    {
        $availableSlots = [];

        $startTime = $workingHour->shift1_start;
        $endTime = $workingHour->shift1_end;

        if ($workingHour->shift2_start && $workingHour->shift2_end) {
            $startTime = min($workingHour->shift1_start, $workingHour->shift2_start);
            $endTime = max($workingHour->shift1_end, $workingHour->shift2_end);
        }

        $current = Carbon::parse($startTime);
        $end = Carbon::parse($endTime);

        $bookedAppointments = Appointment::where('barber_id', $barberId)
            ->whereDate('appointment_date', $date)
            ->whereIn('status', ['pending', 'confirmed'])
            ->get(['appointment_time', 'end_time']);

        while ($current->copy()->addMinutes($duration) <= $end) {
            $slotStart = $current->format('H:i');
            $slotEnd = $current->copy()->addMinutes($duration)->format('H:i');

            $isAvailable = true;

            foreach ($bookedAppointments as $booked) {
                $bookedStart = $booked->appointment_time;
                $bookedEnd = $booked->end_time;

                if ($slotStart < $bookedEnd && $slotEnd > $bookedStart) {
                    $isAvailable = false;
                    break;
                }
            }

            if ($isAvailable) {
                $availableSlots[] = [
                    'time' => $slotStart,
                    'display' => $current->format('g:i A'),
                ];
            }

            $current->addMinutes($duration);
        }

        return $availableSlots;
    }

    /**
     * جلب جميع الخدمات المرتبطة بالحجز
     */
    private function getAppointmentServices(Appointment $appointment): array
    {
        if ($appointment->services_details) {
            return json_decode($appointment->services_details, true);
        }

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
     * الحصول على نص الحالة بالعربية
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

    /**
     * التحقق من إمكانية إلغاء الحجز
     */
    private function canCancelAppointment(Appointment $appointment): bool
    {
        // لا يمكن إلغاء الحجز إذا كان مكتملاً أو ملغياً بالفعل
        if (!in_array($appointment->status, ['pending', 'confirmed'])) {
            return false;
        }

        // لا يمكن إلغاء حجز في الماضي
        $appointmentDateTime = Carbon::parse($appointment->appointment_date . ' ' . $appointment->appointment_time);
        if ($appointmentDateTime->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * جلب جميع حجوزات الزبون
     */
    public function getCustomerAppointments(User $customer, ?string $status = null): AuthResult
    {
        try {
            if (!$customer->hasRole('customer')) {
                return AuthResult::error('هذه الخدمة متاحة للزبائن فقط', null, 403);
            }

            $query = Appointment::where('customer_id', $customer->id)
                ->with(['barber', 'salon', 'service']);

            if ($status && in_array($status, ['pending', 'confirmed', 'completed', 'cancelled'])) {
                $query->where('status', $status);
            }

            $appointments = $query->orderBy('appointment_date', 'desc')
                ->orderBy('appointment_time', 'desc')
                ->get();

            $formattedAppointments = $appointments->map(function ($appointment) {
                return [
                    'id' => $appointment->id,
                    'barber_name' => $appointment->barber->name,
                    'barber_phone' => $appointment->barber->phone,
                    'salon_name' => $appointment->salon->name,
                    'salon_address' => $appointment->salon->address,
                    'services' => $this->getAppointmentServices($appointment),
                    'total_price' => $appointment->total_price,
                    'duration_minutes' => $appointment->duration_minutes,
                    'date' => $appointment->appointment_date,
                    'time' => $appointment->appointment_time,
                    'end_time' => $appointment->end_time,
                    'status' => $appointment->status,
                    'status_text' => $this->getStatusText($appointment->status),
                //    'notes' => $appointment->notes,
                    'created_at' => $appointment->created_at,
                    'can_cancel' => $this->canCancelAppointment($appointment),
                ];
            });

            $stats = [
                'total' => $appointments->count(),
                'pending' => $appointments->where('status', 'pending')->count(),
                'confirmed' => $appointments->where('status', 'confirmed')->count(),
                'completed' => $appointments->where('status', 'completed')->count(),
                'cancelled' => $appointments->where('status', 'cancelled')->count(),
            ];

            return AuthResult::success('تم جلب حجوزاتك بنجاح', [
                'statistics' => $stats,
                'appointments' => $formattedAppointments,
            ]);

        } catch (\Exception $e) {
            Log::error('Get customer appointments error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب حجوزاتك', $e->getMessage(), 500);
        }
    }

    /**
     * جلب الحجوزات النشطة (قيد الانتظار + مؤكدة) - للمواعيد القادمة فقط
     */
    public function getActiveAppointments(User $customer): AuthResult
    {
        try {
            if (!$customer->hasRole('customer')) {
                return AuthResult::error('هذه الخدمة متاحة للزبائن فقط', null, 403);
            }

            $appointments = Appointment::where('customer_id', $customer->id)
                ->whereIn('status', ['pending', 'confirmed'])
                ->where('appointment_date', '>=', Carbon::today())
                ->with(['barber', 'salon', 'service'])
                ->orderBy('appointment_date', 'asc')
                ->orderBy('appointment_time', 'asc')
                ->get();

            $formattedAppointments = $appointments->map(function ($appointment) {
                return [
                    'id' => $appointment->id,
                    'barber_name' => $appointment->barber->name,
                    'salon_name' => $appointment->salon->name,
                    'services' => $this->getAppointmentServices($appointment),
                    'total_price' => $appointment->total_price,
                    'date' => $appointment->appointment_date,
                    'time' => $appointment->appointment_time,
                    'end_time' => $appointment->end_time,
                    'status' => $appointment->status,
                    'status_text' => $this->getStatusText($appointment->status),
                    'can_cancel' => $this->canCancelAppointment($appointment),
                ];
            });

            return AuthResult::success('تم جلب الحجوزات النشطة بنجاح', $formattedAppointments);

        } catch (\Exception $e) {
            Log::error('Get active appointments error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب الحجوزات النشطة', $e->getMessage(), 500);
        }
    }

    /**
     * جلب الحجوزات المنتهية (مكتملة + ملغية)
     */
    public function getCompletedAppointments(User $customer): AuthResult
    {
        try {
            if (!$customer->hasRole('customer')) {
                return AuthResult::error('هذه الخدمة متاحة للزبائن فقط', null, 403);
            }

            $appointments = Appointment::where('customer_id', $customer->id)
                ->whereIn('status', ['completed', 'cancelled'])
                ->with(['barber', 'salon', 'service'])
                ->orderBy('appointment_date', 'desc')
                ->orderBy('appointment_time', 'desc')
                ->get();

            $formattedAppointments = $appointments->map(function ($appointment) {
                return [
                    'id' => $appointment->id,
                    'barber_name' => $appointment->barber->name,
                    'salon_name' => $appointment->salon->name,
                    'services' => $this->getAppointmentServices($appointment),
                    'total_price' => $appointment->total_price,
                    'date' => $appointment->appointment_date,
                    'time' => $appointment->appointment_time,
                    'status' => $appointment->status,
                    'status_text' => $this->getStatusText($appointment->status),
                ];
            });

            return AuthResult::success('تم جلب الحجوزات المنتهية بنجاح', $formattedAppointments);

        } catch (\Exception $e) {
            Log::error('Get completed appointments error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب الحجوزات المنتهية', $e->getMessage(), 500);
        }
    }

    /**
     * إلغاء حجز من قبل الزبون
     */
    public function cancelAppointment(User $customer, int $appointmentId, ?string $reason = null): AuthResult
    {
        try {
            return DB::transaction(function () use ($customer, $appointmentId, $reason) {

                $appointment = Appointment::where('customer_id', $customer->id)
                    ->where('id', $appointmentId)
                    ->first();

                if (!$appointment) {
                    return AuthResult::error('الحجز غير موجود', null, 404);
                }

                if (!in_array($appointment->status, ['pending', 'confirmed'])) {
                    return AuthResult::error('لا يمكن إلغاء هذا الحجز، حالته الحالية: ' . $appointment->status, null, 400);
                }

                $appointmentDateTime = Carbon::parse($appointment->appointment_date . ' ' . $appointment->appointment_time);
                if ($appointmentDateTime->isPast()) {
                    return AuthResult::error('لا يمكن إلغاء موعد بدأ بالفعل', null, 400);
                }

                $appointment->status = 'cancelled';
                $appointment->cancellation_reason = $reason ?? 'تم الإلغاء من قبل العميل';
                $appointment->cancelled_by = $customer->id;
                $appointment->cancelled_at = now();
                $appointment->save();

                return AuthResult::success('تم إلغاء الحجز بنجاح', [
                    'id' => $appointment->id,
                    'status' => $appointment->status,
                    'cancelled_at' => $appointment->cancelled_at,
                ]);

            });
        } catch (\Exception $e) {
            Log::error('Cancel appointment error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء إلغاء الحجز', $e->getMessage(), 500);
        }
    }
}
