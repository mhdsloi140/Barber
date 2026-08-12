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


    private function formatDate($date): ?string
    {
        if (!$date)
            return null;
        if ($date instanceof Carbon) {
            return $date->format('Y-m-d');
        }
        return Carbon::parse($date)->format('Y-m-d');
    }


    private function formatTime($time): ?string
    {
        if (!$time)
            return null;
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

    public function isTimeSlotAvailable(int $barberId, string $date, string $time, ?int $excludeAppointmentId = null): bool
    {
        $query = Appointment::where('barber_id', $barberId)
            ->where('appointment_date', $date)
            ->where('appointment_time', $time)
            ->where('status', '!=', 'cancelled');

        if ($excludeAppointmentId) {
            $query->where('id', '!=', $excludeAppointmentId);
        }

        return !$query->exists();
    }




public function getAvailableTimeSlots(int $barberId, string $date): array
{
    try {
        // 1. جلب الحلاق
        $barber = User::find($barberId);
        if (!$barber || !$barber->hasRole('barber')) {
            return [];
        }

        // 2. جلب اليوم من التاريخ
        $dayOfWeek = strtolower(Carbon::parse($date)->format('l'));

        // 3. جلب أوقات عمل الحلاق
        $barberWorkingHours = $barber->workingHours()
            ->where('day_of_week', $dayOfWeek)
            ->where('is_open', true)
            ->first();

        if (!$barberWorkingHours) {
            return [];
        }

        // 4. جلب أوقات عمل الصالون
        $salon = $barber->salons()->first();
        if (!$salon) {
            return [];
        }

        $salonWorkingHours = $salon->workingHours()
            ->where('day_of_week', $dayOfWeek)
            ->where('is_open', true)
            ->first();

        if (!$salonWorkingHours) {
            return [];
        }

        // 5. تحديد وقت البدء والنهاية
        $startTime = max($barberWorkingHours->shift1_start, $salonWorkingHours->shift1_start);
        $endTime = min($barberWorkingHours->shift1_end, $salonWorkingHours->shift1_end);

        if ($startTime >= $endTime) {
            return [];
        }

        // 6. توليد الأوقات
        $allTimes = [];
        $current = Carbon::parse($startTime);
        $end = Carbon::parse($endTime);

        while ($current < $end) {
            $allTimes[] = $current->format('H:i');
            $current->addMinutes(30);
        }

        // 7. ✅ جلب الأوقات المتاحة (تستبعد الملغاة تلقائياً)
        return Appointment::getAvailableTimes($barberId, $date, $allTimes);

    } catch (\Exception $e) {
        Log::error('Get available time slots error: ' . $e->getMessage());
        return [];
    }
}
    public function getAvailableTimeSlotsWithDetails(int $barberId, string $date): array
    {
        try {
            $availableTimes = $this->getAvailableTimeSlots($barberId, $date);

            $result = [];
            foreach ($availableTimes as $time) {
                $result[] = [
                    'time' => $time,
                    'is_available' => true,
                    'barber_id' => $barberId,
                    'date' => $date,
                ];
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Get available time slots with details error: ' . $e->getMessage());
            return [];
        }
    }

    private function getDateRangeByPeriod(string $period, ?string $month = null): ?array
    {
        $validPeriods = ['today', 'yesterday', 'week1', 'week2', 'week3', 'week4', 'week5', 'month'];

        if (!in_array($period, $validPeriods)) {
            Log::warning('Invalid period value', ['period' => $period]);
            return null;
        }

        $targetDate = $month ? Carbon::parse($month) : Carbon::now();
        $year = $targetDate->year;
        $monthNum = $targetDate->month;

        switch ($period) {
            case 'today':
                return [
                    'start' => Carbon::now()->startOfDay(),
                    'end' => Carbon::now()->endOfDay(),
                ];

            case 'yesterday':
                return [
                    'start' => Carbon::now()->subDay()->startOfDay(),
                    'end' => Carbon::now()->subDay()->endOfDay(),
                ];

            case 'week1':
                return [
                    'start' => Carbon::create($year, $monthNum, 1)->startOfDay(),
                    'end' => Carbon::create($year, $monthNum, 7)->endOfDay(),
                ];

            case 'week2':
                return [
                    'start' => Carbon::create($year, $monthNum, 8)->startOfDay(),
                    'end' => Carbon::create($year, $monthNum, 14)->endOfDay(),
                ];

            case 'week3':
                return [
                    'start' => Carbon::create($year, $monthNum, 15)->startOfDay(),
                    'end' => Carbon::create($year, $monthNum, 21)->endOfDay(),
                ];

            case 'week4':
                return [
                    'start' => Carbon::create($year, $monthNum, 22)->startOfDay(),
                    'end' => Carbon::create($year, $monthNum, 28)->endOfDay(),
                ];

            case 'week5':
                $lastDayOfMonth = Carbon::create($year, $monthNum, 1)->endOfMonth()->day;
                return [
                    'start' => Carbon::create($year, $monthNum, 29)->startOfDay(),
                    'end' => Carbon::create($year, $monthNum, $lastDayOfMonth)->endOfDay(),
                ];

            case 'month':
                return [
                    'start' => Carbon::create($year, $monthNum, 1)->startOfDay(),
                    'end' => Carbon::create($year, $monthNum, 1)->endOfMonth(),
                ];

            default:
                return null;
        }
    }


    private function getPeriodName(?string $period): ?string
    {
        $names = [
            'today' => 'اليوم',
            'yesterday' => 'أمس',
            'week1' => 'الأسبوع الأول (1-7)',
            'week2' => 'الأسبوع الثاني (8-14)',
            'week3' => 'الأسبوع الثالث (15-21)',
            'week4' => 'الأسبوع الرابع (22-28)',
            'week5' => 'الأسبوع الخامس (29-نهاية الشهر)',
            'month' => 'هذا الشهر',
        ];

        return $names[$period] ?? null;
    }

    private function isValidDate(?string $date): bool
    {
        if (!$date)
            return false;

        try {
            Carbon::parse($date);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }


    private function formatDateTime($datetime): ?string
    {
        if (!$datetime)
            return null;
        if ($datetime instanceof Carbon) {
            return $datetime->format('Y-m-d H:i:s');
        }
        return Carbon::parse($datetime)->format('Y-m-d H:i:s');
    }
    public function getWeeksInfo(?string $month = null): array
    {
        $targetDate = $month ? Carbon::parse($month) : Carbon::now();
        $year = $targetDate->year;
        $monthNum = $targetDate->month;
        $lastDay = Carbon::create($year, $monthNum, 1)->endOfMonth()->day;

        $weeks = [];

        $weeks[] = [
            'week' => 1,
            'name' => 'الأسبوع الأول',
            'start_day' => 1,
            'end_day' => 7,
            'start_date' => Carbon::create($year, $monthNum, 1)->toDateString(),
            'end_date' => Carbon::create($year, $monthNum, 7)->toDateString(),
        ];

        $weeks[] = [
            'week' => 2,
            'name' => 'الأسبوع الثاني',
            'start_day' => 8,
            'end_day' => 14,
            'start_date' => Carbon::create($year, $monthNum, 8)->toDateString(),
            'end_date' => Carbon::create($year, $monthNum, 14)->toDateString(),
        ];

        $weeks[] = [
            'week' => 3,
            'name' => 'الأسبوع الثالث',
            'start_day' => 15,
            'end_day' => 21,
            'start_date' => Carbon::create($year, $monthNum, 15)->toDateString(),
            'end_date' => Carbon::create($year, $monthNum, 21)->toDateString(),
        ];

        $weeks[] = [
            'week' => 4,
            'name' => 'الأسبوع الرابع',
            'start_day' => 22,
            'end_day' => 28,
            'start_date' => Carbon::create($year, $monthNum, 22)->toDateString(),
            'end_date' => Carbon::create($year, $monthNum, 28)->toDateString(),
        ];

        if ($lastDay >= 29) {
            $weeks[] = [
                'week' => 5,
                'name' => 'الأسبوع الخامس',
                'start_day' => 29,
                'end_day' => $lastDay,
                'start_date' => Carbon::create($year, $monthNum, 29)->toDateString(),
                'end_date' => Carbon::create($year, $monthNum, $lastDay)->toDateString(),
            ];
        }

        return $weeks;
    }


    public function getSalonAppointments(
        User $salonOwner,
        ?string $search = null,
        ?string $status = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $barberId = null,
        ?string $period = null,
        ?string $month = null,
        int $perPage = 10,
        int $page = 1
    ): AuthResult {
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

            if ($barberId && is_numeric($barberId)) {
                $query->where('barber_id', $barberId);
            }

            if ($status && in_array($status, ['pending', 'confirmed', 'completed', 'cancelled'])) {
                $query->where('status', $status);
            }

            if ($period) {
                $dateRange = $this->getDateRangeByPeriod($period, $month);
                if ($dateRange) {
                    $query->whereBetween('appointment_date', [$dateRange['start'], $dateRange['end']]);
                }
            }

            if ($dateFrom && $this->isValidDate($dateFrom) && !$period) {
                $query->whereDate('appointment_date', '>=', Carbon::parse($dateFrom)->startOfDay());
            }

            if ($dateTo && $this->isValidDate($dateTo) && !$period) {
                $query->whereDate('appointment_date', '<=', Carbon::parse($dateTo)->endOfDay());
            }

            if ($dateFrom && !$dateTo && !$period) {
                $query->whereDate('appointment_date', Carbon::parse($dateFrom)->toDateString());
            }

            $appointments = $query->orderBy('appointment_date', 'asc')
                ->orderBy('appointment_time', 'asc')
                ->paginate($perPage, ['*'], 'page', $page);

            $formattedAppointments = collect($appointments->items())->map(function ($appointment) {
                $services = $this->getAppointmentServices($appointment);
                $totalPrice = $this->calculateTotalPrice($appointment, $services);
                $totalDuration = $this->calculateTotalDuration($appointment, $services);
                $serviceNames = collect($services)->pluck('name')->implode(' + ');

                $barber = $appointment->barber;
                $barberData = null;
                if ($barber) {
                    $barberData = [
                        'id' => $barber->id,
                        'name' => $barber->name,
                        'phone' => $barber->phone,
                        'email' => $barber->email ?? null,
                        'avatar' => $barber->getAvatarUrlAttribute(),
                        'is_active' => $barber->is_active,
                        'created_at' => $barber->created_at,
                    ];
                }

                $customer = $appointment->customer;
                $customerData = null;
                if ($customer) {
                    $customerData = [
                        'id' => $customer->id,
                        'name' => $customer->name,
                        'phone' => $customer->phone,
                        'avatar' => $customer->getAvatarUrlAttribute(),
                        'is_active' => $customer->is_active,
                        'created_at' => $customer->created_at,
                    ];
                }

                return [
                    'id' => $appointment->id,
                    'customer' => $customerData,
                    'barber' => $barberData,
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

            $statsQuery = Appointment::where('salon_id', $salon->id);

            if ($barberId && is_numeric($barberId)) {
                $statsQuery->where('barber_id', $barberId);
            }

            if ($status && in_array($status, ['pending', 'confirmed', 'completed', 'cancelled'])) {
                $statsQuery->where('status', $status);
            }

            if ($period) {
                $dateRange = $this->getDateRangeByPeriod($period, $month);
                if ($dateRange) {
                    $statsQuery->whereBetween('appointment_date', [$dateRange['start'], $dateRange['end']]);
                }
            }

            if ($dateFrom && $this->isValidDate($dateFrom) && !$period) {
                $statsQuery->whereDate('appointment_date', '>=', Carbon::parse($dateFrom)->startOfDay());
            }
            if ($dateTo && $this->isValidDate($dateTo) && !$period) {
                $statsQuery->whereDate('appointment_date', '<=', Carbon::parse($dateTo)->endOfDay());
            }

            $stats = [
                'total' => $appointments->total(),
                'pending' => (clone $statsQuery)->where('status', 'pending')->count(),
                'confirmed' => (clone $statsQuery)->where('status', 'confirmed')->count(),
                'completed' => (clone $statsQuery)->where('status', 'completed')->count(),
                'cancelled' => (clone $statsQuery)->where('status', 'cancelled')->count(),
                'today' => Appointment::where('salon_id', $salon->id)->whereDate('appointment_date', now()->toDateString())->count(),
            ];

            $filterInfo = [
                'search' => $search,
                'status' => $status,
                'barber_id' => $barberId,
                'period' => $period,
                'period_name' => $this->getPeriodName($period),
                'month' => $month,
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
                    ->whereHas('salons', function ($q) use ($salon) {
                        $q->where('salon_id', $salon->id);
                    })
                    ->where('name', 'like', '%' . $search . '%')
                    ->first();

                if ($barber) {
                    $response['searched_barber'] = [
                        'id' => $barber->id,
                        'name' => $barber->name,
                        'phone' => $barber->phone,
                        'avatar' => $barber->getAvatarUrlAttribute(),
                    ];
                }
            }

            return AuthResult::success('تم جلب حجوزات الصالون بنجاح', $response);

        } catch (\Exception $e) {
            Log::error('Get salon appointments error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب الحجوزات', $e->getMessage(), 500);
        }
    }


  public function cancelAppointment(User $salonOwner, int $appointmentId, ?string $reason = null): AuthResult
    {
        try {
            return DB::transaction(function () use ($salonOwner, $appointmentId, $reason) {

                if (!$salonOwner->hasRole('salon_owner')) {
                    return AuthResult::error('هذه الخدمة متاحة لأصحاب الصالونات فقط', null, 403);
                }
                $salon = $salonOwner->ownedSalon;
                if (!$salon) {
                    return AuthResult::error('لا يوجد صالون تابع لك', null, 404);
                }
                $appointment = Appointment::where('id', $appointmentId)
                    ->where('salon_id', $salon->id)
                    ->first();

                if (!$appointment) {
                    return AuthResult::error('الحجز غير موجود أو لا يتبع صالونك', null, 404);
                }
                if (!$appointment->canBeCancelled()) {
                    return AuthResult::error("لا يمكن إلغاء هذا الحجز، حالته الحالية: {$appointment->status}", null, 400);
                }
                $appointment->cancel('salon_owner', $reason);
                $isAvailable = Appointment::isTimeSlotAvailable(
                    $appointment->barber_id,
                    $appointment->appointment_date,
                    $appointment->appointment_time,
                    $appointment->id
                );

                Log::info('Appointment cancelled by salon owner', [
                    'appointment_id' => $appointmentId,
                    'salon_id' => $salon->id,
                    'barber_id' => $appointment->barber_id,
                    'is_available' => $isAvailable,
                    'reason' => $reason
                ]);

                return AuthResult::success('تم إلغاء الحجز بنجاح، الوقت أصبح متاحاً للحجز مرة أخرى', [
                    'id' => $appointment->id,
                    'status' => $appointment->status,
                    'cancelled_at' => $appointment->cancelled_at,
                    'is_available' => $isAvailable,
                    'message' => 'تم إلغاء الحجز، يمكن حجز هذا الوقت مرة أخرى'
                ]);

            });
        } catch (\Exception $e) {
            Log::error('Cancel appointment error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء إلغاء الحجز: ' . $e->getMessage(), null, 500);
        }
    }

}
