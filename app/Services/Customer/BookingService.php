<?php
// app/Services/Customer/BookingService.php

namespace App\Services\Customer;

use App\Models\User;
use App\Models\Salon;
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
     * التحقق من أن الصالون مفتوح في التاريخ المحدد
     */
    private function isSalonOpenOnDate(Salon $salon, string $date): bool
    {
        $dateObj = Carbon::parse($date);
        $dayOfWeek = strtolower($dateObj->format('l'));

        $workingHour = $salon->workingHours()
            ->where('day_of_week', $dayOfWeek)
            ->where('is_open', true)
            ->first();

        return $workingHour !== null;
    }

    /**
     * التحقق من أن الوقت ضمن ساعات عمل الصالون
     */
    private function isTimeWithinSalonHours(Salon $salon, string $date, string $startTime, int $duration): bool
    {
        $dateObj = Carbon::parse($date);
        $dayOfWeek = strtolower($dateObj->format('l'));

        $startTimeFormatted = Carbon::parse($startTime)->format('H:i:s');
        $endTime = Carbon::parse($startTime)->addMinutes($duration)->format('H:i:s');

        $workingHour = $salon->workingHours()
            ->where('day_of_week', $dayOfWeek)
            ->where('is_open', true)
            ->first();

        if (!$workingHour) {
            return false;
        }

        if ($workingHour->shift1_start && $workingHour->shift1_end) {
            if ($startTimeFormatted >= $workingHour->shift1_start && $endTime <= $workingHour->shift1_end) {
                return true;
            }
        }

        if ($workingHour->shift2_start && $workingHour->shift2_end) {
            if ($startTimeFormatted >= $workingHour->shift2_start && $endTime <= $workingHour->shift2_end) {
                return true;
            }
        }

        return false;
    }

    /**
     * حساب الوقت المتبقي بالدقائق بين وقتين
     */
    private function getRemainingMinutes(string $startTime, string $endTime): int
    {
        $start = Carbon::parse($startTime);
        $end = Carbon::parse($endTime);
        return $end->diffInMinutes($start);
    }

    /**
     * التحقق من أن الوقت المتبقي للحلاق كافٍ لإكمال الخدمة
     */
    private function isBarberTimeSufficient(User $barber, string $date, string $startTime, int $duration): array
    {
        $dateObj = Carbon::parse($date);
        $dayOfWeek = strtolower($dateObj->format('l'));
        $startTimeFormatted = Carbon::parse($startTime)->format('H:i:s');
        $endTime = Carbon::parse($startTime)->addMinutes($duration)->format('H:i:s');

        $workingHour = $barber->workingHours()
            ->where('day_of_week', $dayOfWeek)
            ->where('is_open', true)
            ->first();

        if (!$workingHour) {
            return ['valid' => false, 'message' => 'الحلاق لا يعمل في هذا اليوم'];
        }

        if ($workingHour->shift1_start && $workingHour->shift1_end) {
            if ($startTimeFormatted >= $workingHour->shift1_start && $endTime <= $workingHour->shift1_end) {
                return ['valid' => true, 'message' => ''];
            }

            if ($startTimeFormatted >= $workingHour->shift1_start && $startTimeFormatted < $workingHour->shift1_end) {
                $remaining = $this->getRemainingMinutes($startTimeFormatted, $workingHour->shift1_end);
                if ($remaining < $duration) {
                    return [
                        'valid' => false,
                        'message' => "الوقت المتبقي للحلاق غير كافٍ (يتبقى {$remaining} دقيقة فقط، وتحتاج {$duration} دقيقة)"
                    ];
                }
            }
        }

        if ($workingHour->shift2_start && $workingHour->shift2_end) {
            if ($startTimeFormatted >= $workingHour->shift2_start && $endTime <= $workingHour->shift2_end) {
                return ['valid' => true, 'message' => ''];
            }

            if ($startTimeFormatted >= $workingHour->shift2_start && $startTimeFormatted < $workingHour->shift2_end) {
                $remaining = $this->getRemainingMinutes($startTimeFormatted, $workingHour->shift2_end);
                if ($remaining < $duration) {
                    return [
                        'valid' => false,
                        'message' => "الوقت المتبقي للحلاق غير كافٍ (يتبقى {$remaining} دقيقة فقط، وتحتاج {$duration} دقيقة)"
                    ];
                }
            }
        }

        return ['valid' => false, 'message' => 'الوقت المحدد خارج ساعات عمل الحلاق'];
    }

    /**
     * جلب الأوقات المتاحة فقط (بدون الأوقات المحجوزة)
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
            ->get();

        while ($current->copy()->addMinutes($duration) <= $end) {
            $slotStart = $current->format('H:i');
            $slotEnd = $current->copy()->addMinutes($duration)->format('H:i');

            $isAvailable = true;

            foreach ($bookedAppointments as $booked) {
                $bookedStart = $booked->appointment_time instanceof Carbon
                    ? $booked->appointment_time->format('H:i')
                    : substr($booked->appointment_time, 0, 5);

                $bookedEnd = $booked->end_time instanceof Carbon
                    ? $booked->end_time->format('H:i')
                    : substr($booked->end_time, 0, 5);

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
     * جلب صورة الصالون (أول صورة)
     */
    private function getSalonImage($salon): ?string
    {
        try {
            $image = $salon->getFirstMediaUrl('salon_images', 'thumb');
            return $image ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * جلب جميع صور الصالون
     */
    private function getSalonImages($salon): array
    {
        try {
            return $salon->getMedia('salon_images')->map(fn($image) => [
                'id' => $image->id,
                'original' => $image->getUrl(),
                'thumb' => $image->getUrl('thumb'),
                'medium' => $image->getUrl('medium'),
            ])->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * تنسيق بيانات الصالون
     */
    private function formatSalonData($salon): array
    {
        return [
            'id' => $salon->id,
            'name' => $salon->name,
            'address' => $salon->address,
            'phone' => $salon->phone,
            'image' => $this->getSalonImage($salon),
            'images' => $this->getSalonImages($salon),
        ];
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
                return $services->map(fn($service) => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'price' => $service->price,
                    'duration_minutes' => $service->duration_minutes,
                ])->toArray();
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

    /**
     * نص الحالة بالعربية
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
     * اسم اليوم بالعربية
     */
    private function getArabicDayName(string $day): string
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
        return $days[$day] ?? $day;
    }

    /**
     * التحقق من إمكانية إلغاء الحجز
     */
    private function canCancelAppointment(Appointment $appointment): bool
    {
        if (!in_array($appointment->status, ['pending', 'confirmed'])) {
            return false;
        }

        $date = $appointment->appointment_date instanceof Carbon
            ? $appointment->appointment_date->format('Y-m-d')
            : (is_string($appointment->appointment_date) ? $appointment->appointment_date : '');

        $time = $appointment->appointment_time instanceof Carbon
            ? $appointment->appointment_time->format('H:i:s')
            : (is_string($appointment->appointment_time) ? $appointment->appointment_time : '00:00:00');

        $appointmentDateTime = Carbon::parse($date . ' ' . $time);
        return !$appointmentDateTime->isPast();
    }

    /**
     * حفظ الحجز الجديد
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

                $salon = Salon::where('is_active', true)->find($data['salon_id']);
                if (!$salon) {
                    return AuthResult::error('الصالون غير موجود', null, 404);
                }

                $appointmentDate = $data['appointment_date'] ?? $this->getNextDateFromDay($data['day']);

                if (!$this->isSalonOpenOnDate($salon, $appointmentDate)) {
                    $dayName = Carbon::parse($appointmentDate)->format('l');
                    return AuthResult::error("الصالون مغلق في يوم " . $this->getArabicDayName($dayName), null, 400);
                }

                $barber = User::where('is_active', true)
                    ->where('id', $data['barber_id'])
                    ->whereHas('salons', fn($q) => $q->where('salon_id', $data['salon_id']))
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

                $appointmentTime = $data['time'];
                $appointmentTimeFormatted = Carbon::parse($appointmentTime)->format('H:i:s');
                $endTime = Carbon::parse($appointmentTime)->addMinutes($totalDuration)->format('H:i:s');

                if (!$this->isTimeWithinSalonHours($salon, $appointmentDate, $appointmentTimeFormatted, $totalDuration)) {
                    return AuthResult::error('الوقت المحدد خارج ساعات عمل الصالون', null, 400);
                }

                $dayOfWeek = strtolower(Carbon::parse($appointmentDate)->format('l'));
                $workingHour = $barber->workingHours()
                    ->where('day_of_week', $dayOfWeek)
                    ->where('is_open', true)
                    ->first();

                if (!$workingHour) {
                    return AuthResult::error('الحلاق لا يعمل في هذا اليوم', null, 400);
                }

                $isValidTime = false;
                if ($workingHour->shift1_start && $workingHour->shift1_end) {
                    if ($appointmentTimeFormatted >= $workingHour->shift1_start && $endTime <= $workingHour->shift1_end) {
                        $isValidTime = true;
                    }
                }

                if (!$isValidTime && $workingHour->shift2_start && $workingHour->shift2_end) {
                    if ($appointmentTimeFormatted >= $workingHour->shift2_start && $endTime <= $workingHour->shift2_end) {
                        $isValidTime = true;
                    }
                }

                if (!$isValidTime) {
                    return AuthResult::error('الوقت المحدد خارج ساعات عمل الحلاق', null, 400);
                }

                $timeCheck = $this->isBarberTimeSufficient($barber, $appointmentDate, $appointmentTime, $totalDuration);
                if (!$timeCheck['valid']) {
                    return AuthResult::error($timeCheck['message'], null, 400);
                }

                $conflictingBooking = Appointment::where('barber_id', $data['barber_id'])
                    ->whereDate('appointment_date', $appointmentDate)
                    ->whereIn('status', ['pending', 'confirmed', 'cancelled'])
                    ->where(function ($query) use ($appointmentTimeFormatted, $endTime) {
                        $query->where(function ($q) use ($appointmentTimeFormatted, $endTime) {
                            $q->where('appointment_time', '<', $endTime)
                                ->where('end_time', '>', $appointmentTimeFormatted);
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

                    if (empty($availableTimes)) {
                        return AuthResult::error('لا توجد أوقات متاحة في هذا اليوم، يرجى اختيار يوم آخر', null, 400);
                    }

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
                    'appointment_time' => $appointmentTimeFormatted,
                    'end_time' => $endTime,
                    'status' => 'pending',
                    'total_price' => $totalPrice,
                    'duration_minutes' => $totalDuration,
                    'notes' => $data['notes'] ?? null,
                ]);

                $appointment->load(['customer', 'barber', 'salon']);

                try {
                    $notificationService = app(FirebaseNotificationService::class);
                    $notificationService->notifyNewAppointment($salon, $appointment);
                } catch (\Exception $e) {
                    Log::error('Failed to send notification: ' . $e->getMessage());
                }

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
                        'salon' => $this->formatSalonData($salon),
                        'services' => $services->map(fn($service) => [
                            'id' => $service->id,
                            'name' => $service->name,
                            'price' => $service->price,
                            'duration_minutes' => $service->duration_minutes,
                        ]),
                        'services_summary' => $totals['services_names'],
                        'total_duration' => $totalDuration,
                        'total_price' => $totalPrice,
                        'date' => $appointment->appointment_date,
                        'day' => $data['day'] ?? null,
                        'time' => $appointment->appointment_time,
                        'end_time' => $appointment->end_time,
                        'status' => $appointment->status,
                        'notes' => $appointment->notes,
                        'created_at' => $appointment->created_at,
                    ],
                ], 201);
            });
        } catch (\Exception $e) {
            return AuthResult::error('حدث خطأ أثناء إنشاء الحجز: ' . $e->getMessage(), null, 500);
        }
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
                ->with(['barber', 'salon']);

            if ($status && in_array($status, ['pending', 'confirmed', 'completed', 'cancelled'])) {
                $query->where('status', $status);
            }

            $appointments = $query->orderBy('appointment_date', 'desc')
                ->orderBy('appointment_time', 'desc')
                ->get();

            $formattedAppointments = $appointments->map(fn($appointment) => [
                'id' => $appointment->id,
                'barber_name' => $appointment->barber->name,
                'barber_phone' => $appointment->barber->phone,
                'barber_avatar' => $appointment->barber->getAvatarUrlAttribute(),
                'salon' => $this->formatSalonData($appointment->salon),
                'services' => $this->getAppointmentServices($appointment),
                'total_price' => $appointment->total_price,
                'duration_minutes' => $appointment->duration_minutes,
                'date' => $appointment->appointment_date instanceof Carbon
                    ? $appointment->appointment_date->format('Y-m-d')
                    : date('Y-m-d', strtotime($appointment->appointment_date)),
                'time' => $appointment->appointment_time instanceof Carbon
                    ? $appointment->appointment_time->format('H:i')
                    : (is_string($appointment->appointment_time) ? substr($appointment->appointment_time, 0, 5) : '00:00'),
                'end_time' => $appointment->end_time instanceof Carbon
                    ? $appointment->end_time->format('H:i')
                    : (is_string($appointment->end_time) ? substr($appointment->end_time, 0, 5) : '00:00'),
                'status' => $appointment->status,
                'status_text' => $this->getStatusText($appointment->status),
                'notes' => $appointment->notes,
                'created_at' => $appointment->created_at,
                'can_cancel' => $this->canCancelAppointment($appointment),
            ]);

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
     * جلب الحجوزات النشطة
     */
    public function getActiveAppointments(User $customer): AuthResult
    {
        try {
            if (!$customer->hasRole('customer')) {
                return AuthResult::error('هذه الخدمة متاحة للزبائن فقط', null, 403);
            }

            $today = Carbon::today()->format('Y-m-d');

            $appointments = Appointment::where('customer_id', $customer->id)
                ->whereIn('status', ['pending', 'confirmed'])
                ->whereDate('appointment_date', '>=', $today)
                ->with(['barber', 'salon'])
                ->orderBy('appointment_date', 'asc')
                ->orderBy('appointment_time', 'asc')
                ->get();

            $formattedAppointments = $appointments->map(fn($appointment) => [
                'id' => $appointment->id,
                'barber_name' => $appointment->barber->name,
                'barber_avatar' => $appointment->barber->getAvatarUrlAttribute(),
                'salon' => $this->formatSalonData($appointment->salon),
                'services' => $this->getAppointmentServices($appointment),
                'total_price' => $appointment->total_price,
                'date' => $appointment->appointment_date instanceof Carbon
                    ? $appointment->appointment_date->format('Y-m-d')
                    : date('Y-m-d', strtotime($appointment->appointment_date)),
                'time' => $appointment->appointment_time instanceof Carbon
                    ? $appointment->appointment_time->format('H:i')
                    : (is_string($appointment->appointment_time) ? substr($appointment->appointment_time, 0, 5) : '00:00'),
                'end_time' => $appointment->end_time instanceof Carbon
                    ? $appointment->end_time->format('H:i')
                    : (is_string($appointment->end_time) ? substr($appointment->end_time, 0, 5) : '00:00'),
                'status' => $appointment->status,
                'status_text' => $this->getStatusText($appointment->status),
                'can_cancel' => $this->canCancelAppointment($appointment),
            ]);

            return AuthResult::success('تم جلب الحجوزات النشطة بنجاح', $formattedAppointments);
        } catch (\Exception $e) {
            Log::error('Get active appointments error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب الحجوزات النشطة', $e->getMessage(), 500);
        }
    }

    /**
     * جلب الحجوزات المكتملة فقط
     */
    public function getCompletedAppointments(User $customer): AuthResult
    {
        try {
            if (!$customer->hasRole('customer')) {
                return AuthResult::error('هذه الخدمة متاحة للزبائن فقط', null, 403);
            }

            $appointments = Appointment::where('customer_id', $customer->id)
                ->where('status', 'completed')
                ->with(['barber', 'salon'])
                ->orderBy('appointment_date', 'desc')
                ->orderBy('appointment_time', 'desc')
                ->get();

            $formattedAppointments = $appointments->map(fn($appointment) => [
                'id' => $appointment->id,
                'barber_name' => $appointment->barber->name,
                'barber_phone' => $appointment->barber->phone,
                'barber_avatar' => $appointment->barber->getAvatarUrlAttribute(),
                'salon' => $this->formatSalonData($appointment->salon),
                'services' => $this->getAppointmentServices($appointment),
                'total_price' => $appointment->total_price,
                'duration_minutes' => $appointment->duration_minutes,
                'date' => $appointment->appointment_date instanceof Carbon
                    ? $appointment->appointment_date->format('Y-m-d')
                    : date('Y-m-d', strtotime($appointment->appointment_date)),
                'time' => $appointment->appointment_time instanceof Carbon
                    ? $appointment->appointment_time->format('H:i')
                    : (is_string($appointment->appointment_time) ? substr($appointment->appointment_time, 0, 5) : '00:00'),
                'end_time' => $appointment->end_time instanceof Carbon
                    ? $appointment->end_time->format('H:i')
                    : (is_string($appointment->end_time) ? substr($appointment->end_time, 0, 5) : '00:00'),
                'status' => $appointment->status,
                'status_text' => $this->getStatusText($appointment->status),
                'notes' => $appointment->notes,
                'created_at' => $appointment->created_at,
            ]);

            $stats = ['total_completed' => $appointments->count()];

            return AuthResult::success('تم جلب الحجوزات المكتملة بنجاح', [

              $formattedAppointments,
            ]);
        } catch (\Exception $e) {
            Log::error('Get completed appointments error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب الحجوزات المكتملة', $e->getMessage(), 500);
        }
    }

    /**
     * جلب الحجوزات الملغية فقط
     */
    public function getCancelledAppointments(User $customer): AuthResult
    {
        try {
            if (!$customer->hasRole('customer')) {
                return AuthResult::error('هذه الخدمة متاحة للزبائن فقط', null, 403);
            }

            $appointments = Appointment::where('customer_id', $customer->id)
                ->where('status', 'cancelled')
                ->with(['barber', 'salon'])
                ->orderBy('appointment_date', 'desc')
                ->orderBy('appointment_time', 'desc')
                ->get();

            $formattedAppointments = $appointments->map(fn($appointment) => [
                'id' => $appointment->id,
                'barber_name' => $appointment->barber->name,
                'barber_phone' => $appointment->barber->phone,
                // 'barber_avatar' => $appointment->barber->getAvatarUrlAttribute(),
                'salon' => $this->formatSalonData($appointment->salon),
                'services' => $this->getAppointmentServices($appointment),
                'total_price' => $appointment->total_price,
                'duration_minutes' => $appointment->duration_minutes,
                'date' => $appointment->appointment_date instanceof Carbon
                    ? $appointment->appointment_date->format('Y-m-d')
                    : date('Y-m-d', strtotime($appointment->appointment_date)),
                'time' => $appointment->appointment_time instanceof Carbon
                    ? $appointment->appointment_time->format('H:i')
                    : (is_string($appointment->appointment_time) ? substr($appointment->appointment_time, 0, 5) : '00:00'),
                'end_time' => $appointment->end_time instanceof Carbon
                    ? $appointment->end_time->format('H:i')
                    : (is_string($appointment->end_time) ? substr($appointment->end_time, 0, 5) : '00:00'),
                'status' => $appointment->status,
                'status_text' => $this->getStatusText($appointment->status),
                'cancellation_reason' => $appointment->cancellation_reason,
                'cancelled_at' => $appointment->cancelled_at,
                'notes' => $appointment->notes,
                'created_at' => $appointment->created_at,
            ]);

            $stats = ['total_cancelled' => $appointments->count()];

            return AuthResult::success('تم جلب الحجوزات الملغية بنجاح', [

              $formattedAppointments,
            ]);
        } catch (\Exception $e) {
            Log::error('Get cancelled appointments error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب الحجوزات الملغية', $e->getMessage(), 500);
        }
    }

    /**
     * جلب الحجوزات المنتهية (مكتملة + ملغية)
     */
    public function getFinishedAppointments(User $customer): AuthResult
    {
        try {
            if (!$customer->hasRole('customer')) {
                return AuthResult::error('هذه الخدمة متاحة للزبائن فقط', null, 403);
            }

            $appointments = Appointment::where('customer_id', $customer->id)
                ->whereIn('status', ['completed', 'cancelled'])
                ->with(['barber', 'salon'])
                ->orderBy('appointment_date', 'desc')
                ->orderBy('appointment_time', 'desc')
                ->get();

            $formattedAppointments = $appointments->map(fn($appointment) => [
                'id' => $appointment->id,
                'barber_name' => $appointment->barber->name,
                'barber_phone' => $appointment->barber->phone,
                'barber_avatar' => $appointment->barber->getAvatarUrlAttribute(),
                'salon' => $this->formatSalonData($appointment->salon),
                'services' => $this->getAppointmentServices($appointment),
                'total_price' => $appointment->total_price,
                'duration_minutes' => $appointment->duration_minutes,
                'date' => $appointment->appointment_date instanceof Carbon
                    ? $appointment->appointment_date->format('Y-m-d')
                    : date('Y-m-d', strtotime($appointment->appointment_date)),
                'time' => $appointment->appointment_time instanceof Carbon
                    ? $appointment->appointment_time->format('H:i')
                    : (is_string($appointment->appointment_time) ? substr($appointment->appointment_time, 0, 5) : '00:00'),
                'end_time' => $appointment->end_time instanceof Carbon
                    ? $appointment->end_time->format('H:i')
                    : (is_string($appointment->end_time) ? substr($appointment->end_time, 0, 5) : '00:00'),
                'status' => $appointment->status,
                'status_text' => $this->getStatusText($appointment->status),
                'cancellation_reason' => $appointment->cancellation_reason,
                'cancelled_at' => $appointment->cancelled_at,
                'notes' => $appointment->notes,
                'created_at' => $appointment->created_at,
            ]);

            $stats = [
                'total_completed' => $appointments->where('status', 'completed')->count(),
                'total_cancelled' => $appointments->where('status', 'cancelled')->count(),
                'total' => $appointments->count(),
            ];

            return AuthResult::success('تم جلب الحجوزات المنتهية بنجاح', [

              $formattedAppointments,
            ]);
        } catch (\Exception $e) {
            Log::error('Get finished appointments error: ' . $e->getMessage());
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
                    $statusText = $this->getStatusText($appointment->status);
                    return AuthResult::error("لا يمكن إلغاء هذا الحجز، حالته الحالية: {$statusText}", null, 400);
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
                    'status_text' => $this->getStatusText($appointment->status),
                    'cancelled_at' => $appointment->cancelled_at,
                    'cancellation_reason' => $appointment->cancellation_reason,
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Cancel appointment error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء إلغاء الحجز: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * تعديل حجز موجود
     */
    public function updateAppointment(User $customer, int $appointmentId, array $data): AuthResult
    {
        try {
            return DB::transaction(function () use ($customer, $appointmentId, $data) {

                $appointment = Appointment::where('customer_id', $customer->id)
                    ->where('id', $appointmentId)
                    ->with(['barber', 'salon'])
                    ->first();

                if (!$appointment) {
                    return AuthResult::error('الحجز غير موجود', null, 404);
                }

                if (!in_array($appointment->status, ['pending', 'confirmed'])) {
                    return AuthResult::error('لا يمكن تعديل هذا الحجز، حالته الحالية: ' . $appointment->status, null, 400);
                }

                $appointmentDateTime = Carbon::parse($appointment->appointment_date . ' ' . $appointment->appointment_time);
                if ($appointmentDateTime->isPast()) {
                    return AuthResult::error('لا يمكن تعديل موعد بدأ بالفعل', null, 400);
                }

                $salon = $appointment->salon;
                $barber = $appointment->barber;

                $newDate = $data['appointment_date'] ?? $appointment->appointment_date;

                if (isset($data['day']) && !isset($data['appointment_date'])) {
                    $newDate = $this->getNextDateFromDay($data['day']);
                }

                if (!$this->isSalonOpenOnDate($salon, $newDate)) {
                    $dayName = Carbon::parse($newDate)->format('l');
                    return AuthResult::error("الصالون مغلق في يوم " . $this->getArabicDayName($dayName), null, 400);
                }

                $newTime = $data['time'] ?? $appointment->appointment_time;

                $serviceIds = $data['service_ids'] ?? null;
                $services = null;
                $totalDuration = $appointment->duration_minutes;
                $totalPrice = $appointment->total_price;

                if ($serviceIds) {
                    $services = BarberService::whereIn('id', $serviceIds)
                        ->where('barber_id', $barber->id)
                        ->where('is_active', true)
                        ->get();

                    if ($services->count() !== count($serviceIds)) {
                        return AuthResult::error('بعض الخدمات غير متاحة لهذا الحلاق', null, 404);
                    }

                    $totals = $this->calculateTotals($services);
                    $totalDuration = $totals['total_duration'];
                    $totalPrice = $totals['total_price'];
                }

                $newTimeFormatted = Carbon::parse($newTime)->format('H:i:s');
                $newEndTime = Carbon::parse($newTime)->addMinutes($totalDuration)->format('H:i:s');

                if (!$this->isTimeWithinSalonHours($salon, $newDate, $newTimeFormatted, $totalDuration)) {
                    return AuthResult::error('الوقت المحدد خارج ساعات عمل الصالون', null, 400);
                }

                $dayOfWeek = strtolower(Carbon::parse($newDate)->format('l'));
                $workingHour = $barber->workingHours()
                    ->where('day_of_week', $dayOfWeek)
                    ->where('is_open', true)
                    ->first();

                if (!$workingHour) {
                    return AuthResult::error('الحلاق لا يعمل في هذا اليوم', null, 400);
                }

                $isValidTime = false;
                if ($workingHour->shift1_start && $workingHour->shift1_end) {
                    if ($newTimeFormatted >= $workingHour->shift1_start && $newEndTime <= $workingHour->shift1_end) {
                        $isValidTime = true;
                    }
                }

                if (!$isValidTime && $workingHour->shift2_start && $workingHour->shift2_end) {
                    if ($newTimeFormatted >= $workingHour->shift2_start && $newEndTime <= $workingHour->shift2_end) {
                        $isValidTime = true;
                    }
                }

                if (!$isValidTime) {
                    return AuthResult::error('الوقت المحدد خارج ساعات عمل الحلاق', null, 400);
                }

                $conflictingBooking = Appointment::where('barber_id', $barber->id)
                    ->where('id', '!=', $appointmentId)
                    ->whereDate('appointment_date', $newDate)
                    ->whereIn('status', ['pending', 'confirmed'])
                    ->where(function ($query) use ($newTimeFormatted, $newEndTime) {
                        $query->where(function ($q) use ($newTimeFormatted, $newEndTime) {
                            $q->where('appointment_time', '<', $newEndTime)
                                ->where('end_time', '>', $newTimeFormatted);
                        });
                    })
                    ->exists();

                if ($conflictingBooking) {
                    return AuthResult::error('هذا الوقت غير متاح، يرجى اختيار وقت آخر', null, 400);
                }

                $appointment->appointment_date = $newDate;
                $appointment->appointment_time = $newTimeFormatted;
                $appointment->end_time = $newEndTime;
                $appointment->duration_minutes = $totalDuration;
                $appointment->total_price = $totalPrice;

                if ($serviceIds) {
                    $appointment->service_id = $serviceIds[0];
                    $appointment->services = json_encode($serviceIds);
                    $appointment->services_details = json_encode($services->toArray());
                }

                if (isset($data['notes'])) {
                    $appointment->notes = $data['notes'];
                }
                $appointment->save();

                $appointment->load(['customer', 'barber', 'salon']);

                $servicesList = $services ?: BarberService::whereIn('id', json_decode($appointment->services ?? '[]'))->get();
                $totals = $this->calculateTotals($servicesList);

                return AuthResult::success('تم تعديل الحجز بنجاح', [
                    'appointment' => [
                        'id' => $appointment->id,
                        'customer' => [
                            'id' => $appointment->customer->id,
                            'name' => $appointment->customer->name,
                        ],
                        'barber' => [
                            'id' => $appointment->barber->id,
                            'name' => $appointment->barber->name,
                        ],
                        'salon' => $this->formatSalonData($salon),
                        'services' => $servicesList->map(fn($service) => [
                            'id' => $service->id,
                            'name' => $service->name,
                            'price' => $service->price,
                            'duration_minutes' => $service->duration_minutes,
                        ]),
                        'services_summary' => $totals['services_names'],
                        'total_duration' => $totalDuration,
                        'total_price' => $totalPrice,
                        'date' => $appointment->appointment_date,
                        'time' => Carbon::parse($appointment->appointment_time)->format('H:i'),
                        'end_time' => Carbon::parse($appointment->end_time)->format('H:i'),
                        'status' => $appointment->status,
                        'status_text' => $this->getStatusText($appointment->status),
                        'notes' => $appointment->notes,
                        'updated_at' => $appointment->updated_at,
                    ],
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Update appointment error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء تعديل الحجز: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * جلب الأوقات المتاحة للحلاق (للاستعلام المباشر)
     */
    public function getAvailableTimes(int $barberId, string $date, int $serviceId): AuthResult
    {
        try {
            $barber = User::find($barberId);
            $service = BarberService::find($serviceId);

            if (!$barber || !$service) {
                return AuthResult::error('البيانات غير صحيحة', null, 404);
            }

            $dayOfWeek = strtolower(Carbon::parse($date)->format('l'));
            $workingHour = $barber->workingHours()
                ->where('day_of_week', $dayOfWeek)
                ->where('is_open', true)
                ->first();

            if (!$workingHour) {
                return AuthResult::error('الحلاق لا يعمل في هذا اليوم', null, 400);
            }

            $availableTimes = $this->getAvailableTimesForBarber(
                $barberId,
                $date,
                $service->duration_minutes,
                $workingHour
            );

            if (empty($availableTimes)) {
                return AuthResult::error('لا توجد أوقات متاحة في هذا اليوم، يرجى اختيار يوم آخر', null, 400);
            }

            return AuthResult::success('تم جلب الأوقات المتاحة بنجاح', $availableTimes);
        } catch (\Exception $e) {
            return AuthResult::error('حدث خطأ أثناء جلب الأوقات المتاحة', $e->getMessage(), 500);
        }
    }
}
