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
     * حفظ الحجز الجديد
     */
    public function storeBooking(array $data): AuthResult
    {
        try {
            return DB::transaction(function () use ($data) {

                // 1. التحقق من وجود المستخدم المسجل
                $customer = auth()->user();

                if (!$customer) {
                    return AuthResult::error('يجب تسجيل الدخول أولاً', null, 401);
                }

                // 2. التحقق من دور المستخدم
                if (!$customer->hasRole('customer')) {
                    return AuthResult::error('هذه الخدمة متاحة للزبائن فقط', null, 403);
                }

                // 3. التحقق من الحلاق
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

                // 4. التحقق من الخدمة
                $service = BarberService::where('id', $data['service_id'])
                    ->where('barber_id', $data['barber_id'])
                    ->where('is_active', true)
                    ->first();

                if (!$service) {
                    return AuthResult::error('الخدمة غير متاحة لهذا الحلاق', null, 404);
                }

                // 5. تحويل اليوم إلى أقرب تاريخ
                $appointmentDate = $this->getNextDateFromDay($data['day']);
                $appointmentTime = $data['time'];

                // حساب وقت الانتهاء
                $endTime = Carbon::parse($appointmentTime)
                    ->addMinutes($service->duration_minutes)
                    ->format('H:i:s');

                // 6. التحقق من ساعات عمل الحلاق
                $workingHour = $barber->workingHours()
                    ->where('day_of_week', $data['day'])
                    ->where('is_open', true)
                    ->first();

                if (!$workingHour) {
                    return AuthResult::error('الحلاق لا يعمل في هذا اليوم', null, 400);
                }

                // التحقق من صحة الوقت (ضمن ساعات العمل)
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

                // 7. التحقق من عدم وجود حجز مسبق يتعارض مع الوقت المطلوب
                //  يسمح بالحجز إذا بدأ في نفس وقت انتهاء حجز آخر
                $conflictingBooking = Appointment::where('barber_id', $data['barber_id'])
                    ->whereDate('appointment_date', $appointmentDate)
                    ->whereIn('status', ['pending', 'confirmed'])
                    ->where(function ($query) use ($appointmentTime, $endTime) {
                        $query->where(function ($q) use ($appointmentTime, $endTime) {
                            // الحجز الجديد يتداخل مع حجز موجود
                            $q->where('appointment_time', '<', $endTime)
                              ->where('end_time', '>', $appointmentTime);
                        });
                    })
                    ->exists();

                if ($conflictingBooking) {
                    // جلب الأوقات المتاحة الأخرى
                    $availableTimes = $this->getAvailableTimesForBarber(
                        $barber->id,
                        $appointmentDate,
                        $service->duration_minutes,
                        $workingHour
                    );

                    return AuthResult::errorWithData(
                        'هذا الوقت غير متاح، يرجى اختيار وقت آخر',
                        ['available_times' => $availableTimes],
                        400
                    );
                }

                // 8. إنشاء الحجز
                $appointment = Appointment::create([
                    'customer_id' => $customer->id,
                    'barber_id' => $data['barber_id'],
                    'salon_id' => $data['salon_id'],
                    'service_id' => $data['service_id'],
                    'appointment_date' => $appointmentDate,
                    'appointment_time' => $appointmentTime,
                    'end_time' => $endTime,
                    'status' => 'pending',
                    'total_price' => $service->price,
                    'duration_minutes' => $service->duration_minutes,
                    'notes' => $data['notes'] ?? null,
                ]);

                $appointment->load(['customer', 'barber', 'salon', 'service']);

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
                        'service' => [
                            'id' => $appointment->service->id,
                            'name' => $appointment->service->name,
                            'price' => $appointment->service->price,
                            'duration_minutes' => $appointment->service->duration_minutes,
                        ],
                        'date' => $appointment->appointment_date,
                        'day' => $data['day'],
                        'time' => $appointment->appointment_time,
                        'end_time' => $appointment->end_time,
                        'total_price' => $appointment->total_price,
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
     * جلب الأوقات المتاحة للحلاق في يوم معين
     */
    private function getAvailableTimesForBarber(int $barberId, string $date, int $duration, $workingHour): array
    {
        $availableSlots = [];

        // تحديد ساعات العمل
        $startTime = $workingHour->shift1_start;
        $endTime = $workingHour->shift1_end;

        if ($workingHour->shift2_start && $workingHour->shift2_end) {
            $startTime = min($workingHour->shift1_start, $workingHour->shift2_start);
            $endTime = max($workingHour->shift1_end, $workingHour->shift2_end);
        }

        $current = Carbon::parse($startTime);
        $end = Carbon::parse($endTime);

        // جلب المواعيد المحجوزة مع أوقات البدء والانتهاء
        $bookedAppointments = Appointment::where('barber_id', $barberId)
            ->whereDate('appointment_date', $date)
            ->whereIn('status', ['pending', 'confirmed'])
            ->get(['appointment_time', 'end_time']);

        // توليد الأوقات المتاحة
        while ($current->copy()->addMinutes($duration) <= $end) {
            $slotStart = $current->format('H:i');
            $slotEnd = $current->copy()->addMinutes($duration)->format('H:i');

            $isAvailable = true;

            // التحقق من عدم تداخل هذا الوقت مع أي حجز موجود
            foreach ($bookedAppointments as $booked) {
                $bookedStart = $booked->appointment_time;
                $bookedEnd = $booked->end_time;

                //  تداخل فقط إذا بدأ الوقت الجديد قبل انتهاء الحجز القائم
                // هذا يسمح بالحجز في 10:30 إذا كان الحجز السابق انتهى في 10:30
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
}
