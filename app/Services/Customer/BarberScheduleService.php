<?php

namespace App\Services\Customer;

use App\Models\User;
use App\Models\Appointment;
use App\Models\BarberService;
use App\Services\AuthResult;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class BarberScheduleService
{
    // أيام الأسبوع بالعربية
    private $daysInArabic = [
        'sunday' => 'الأحد',
        'monday' => 'الإثنين',
        'tuesday' => 'الثلاثاء',
        'wednesday' => 'الأربعاء',
        'thursday' => 'الخميس',
        'friday' => 'الجمعة',
        'saturday' => 'السبت',
    ];

    /**
     * جلب جدول الحلاق الكامل (الخدمات، أوقات الفراغ، الحجوزات)
     */
    public function getBarberSchedule(int $barberId, ?string $date = null): AuthResult
    {
        try {
            // 1. جلب بيانات الحلاق
            $barber = User::where('id', $barberId)
                ->whereHas('roles', function($q) {
                    $q->where('name', 'barber');
                })
                ->with(['salons', 'workingHours' => function($q) {
                    $q->where('is_open', true);
                }])
                ->first();

            if (!$barber) {
                return AuthResult::error('الحلاق غير موجود', null, 404);
            }

            // 2. جلب الصالون التابع للحلاق
            $salon = $barber->salons->first();
            if (!$salon) {
                return AuthResult::error('هذا الحلاق لا يعمل في أي صالون', null, 404);
            }

            // 3. تحديد التاريخ
            $selectedDate = $date ? Carbon::parse($date)->format('Y-m-d') : Carbon::now()->format('Y-m-d');
            $dateObj = Carbon::parse($selectedDate);
            $dayOfWeek = strtolower($dateObj->format('l'));
            $dayName = $this->daysInArabic[$dayOfWeek];

            // 4. جلب أوقات عمل الحلاق في هذا اليوم
            $workingHour = $barber->workingHours()
                ->where('day_of_week', $dayOfWeek)
                ->where('is_open', true)
                ->first();

            // الخدمات
            $services = BarberService::where('barber_id', $barberId)
                ->orderBy('name', 'asc')
                ->get()
                ->map(function($service) {
                    return [
                        'id' => $service->id,
                        'name' => $service->name,
                        'description' => $service->description,
                        'price' => (float) $service->price,
                        'duration_minutes' => (int) $service->duration_minutes,
                        'is_active' => (bool) $service->is_active,
                    ];
                });


            $appointments = Appointment::where('barber_id', $barberId)
                ->whereDate('appointment_date', $selectedDate)
                ->with(['customer'])
                ->orderBy('appointment_time', 'asc')
                ->get();

            // تنسيق الحجوزات
            $formattedAppointments = $appointments->map(function($appointment) {
                return [
                    'id' => $appointment->id,
                    'customer_name' => $appointment->customer->name,
                    'customer_phone' => $appointment->customer->phone,
                    'start_time' => Carbon::parse($appointment->appointment_time)->format('H:i'),
                    'end_time' => Carbon::parse($appointment->end_time)->format('H:i'),
                    'duration_minutes' => $appointment->duration_minutes,
                    'total_price' => (float) $appointment->total_price,
                    'status' => $appointment->status,
                ];
            });


            $freeSlots = [];
            $workingHoursInfo = null;

            if ($workingHour) {
                $workingHoursInfo = [
                    'start' => $workingHour->shift1_start,
                    'end' => $workingHour->shift1_end,
                ];

                // توليد أوقات الفراغ (كل 30 دقيقة افتراضياً)
                $freeSlots = $this->generateFreeSlots(
                    $workingHour->shift1_start,
                    $workingHour->shift1_end,
                    30, // مدة افتراضية 30 دقيقة
                    $appointments
                );
            }

            // 4. إحصائيات الخدمات
            $servicesStatistics = [
                'total' => $services->count(),
                'active' => $services->where('is_active', true)->count(),
                'inactive' => $services->where('is_active', false)->count(),
                // 'min_price' => $services->where('is_active', true)->min('price') ?? 0,
                // 'max_price' => $services->where('is_active', true)->max('price') ?? 0,
            ];

            // بناء الـ Response
            $data = [
                // معلومات الحلاق
                'barber' => [
                    'id' => $barber->id,
                    'name' => $barber->name,
                    'phone' => $barber->phone,
                    'avatar' => $barber->getAvatarUrlAttribute(),
                ],
                // معلومات الصالون
                'salon' => [
                    'id' => $salon->id,
                    'name' => $salon->name,
                    'address' => $salon->address,
                ],
                // معلومات التاريخ
                'selected_date' => $selectedDate,
                'selected_day' => $dayName,
                'is_working_day' => $workingHour !== null,
                'working_hours' => $workingHoursInfo,


                'services' => $services,
                'services_statistics' => $servicesStatistics,
                'services_count' => $services->count(),


                // 'booked_appointments' => $formattedAppointments,
                'booked_appointments_count' => $formattedAppointments->count(),

                // ========== القسم 3: أوقات الفراغ (أوقات متاحة) ==========
                'available' => $freeSlots,
                'available_count' => count($freeSlots),
            ];

            return AuthResult::success('تم جلب بيانات الحلاق بنجاح', $data);

        } catch (\Exception $e) {
            Log::error('Get barber schedule error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب البيانات: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * توليد أوقات الفراغ (فترات زمنية متاحة)
     */
    private function generateFreeSlots(string $startTime, string $endTime, int $slotDuration, $appointments): array
    {
        $slots = [];
        $current = Carbon::parse($startTime);
        $end = Carbon::parse($endTime);

        // تجهيز الأوقات المحجوزة
        $bookedSlots = [];
        foreach ($appointments as $appointment) {
            if (in_array($appointment->status, ['pending', 'confirmed'])) {
                $bookedSlots[] = [
                    'start' => Carbon::parse($appointment->appointment_time),
                    'end' => Carbon::parse($appointment->end_time),
                ];
            }
        }

        while ($current->lt($end)) {
            $slotEnd = (clone $current)->addMinutes($slotDuration);

            if ($slotEnd->lte($end)) {
                $isAvailable = true;

                foreach ($bookedSlots as $booked) {
                    if ($current->lt($booked['end']) && $slotEnd->gt($booked['start'])) {
                        $isAvailable = false;
                        break;
                    }
                }

                if ($isAvailable) {
                    $slots[] = [
                        'start' => $current->format('H:i'),
                        'end' => $slotEnd->format('H:i'),
                    ];
                }
            }

            $current->addMinutes($slotDuration);
        }

        return $slots;
    }
}
