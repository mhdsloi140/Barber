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


    /**
     * الحصول على نطاق التاريخ بناءً على الفترة (حسب أيام الشهر)
     */

private function getDateRangeByPeriod(string $period, ?string $month = null): ?array
{
    // التحقق من أن period هي قيمة صحيحة
    $validPeriods = ['today', 'yesterday', 'week1', 'week2', 'week3', 'week4', 'week5', 'month'];

    if (!in_array($period, $validPeriods)) {
        Log::warning('Invalid period value', ['period' => $period]);
        return null;
    }

    // تحديد الشهر (إذا لم يتم تحديده، استخدم الشهر الحالي)
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


    /**
     * الحصول على اسم الفترة بالعربية
     */
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


    /**
     * التحقق من صحة التاريخ
     */
    private function isValidDate(?string $date): bool
    {
        if (!$date) return false;

        try {
            Carbon::parse($date);
            return true;
        } catch (\Exception $e) {
            return false;
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


    /**
     * الحصول على معلومات الأسابيع في الشهر
     */
    public function getWeeksInfo(?string $month = null): array
    {
        $targetDate = $month ? Carbon::parse($month) : Carbon::now();
        $year = $targetDate->year;
        $monthNum = $targetDate->month;
        $lastDay = Carbon::create($year, $monthNum, 1)->endOfMonth()->day;

        $weeks = [];

        // الأسبوع الأول (1-7)
        $weeks[] = [
            'week' => 1,
            'name' => 'الأسبوع الأول',
            'start_day' => 1,
            'end_day' => 7,
            'start_date' => Carbon::create($year, $monthNum, 1)->toDateString(),
            'end_date' => Carbon::create($year, $monthNum, 7)->toDateString(),
        ];

        // الأسبوع الثاني (8-14)
        $weeks[] = [
            'week' => 2,
            'name' => 'الأسبوع الثاني',
            'start_day' => 8,
            'end_day' => 14,
            'start_date' => Carbon::create($year, $monthNum, 8)->toDateString(),
            'end_date' => Carbon::create($year, $monthNum, 14)->toDateString(),
        ];

        // الأسبوع الثالث (15-21)
        $weeks[] = [
            'week' => 3,
            'name' => 'الأسبوع الثالث',
            'start_day' => 15,
            'end_day' => 21,
            'start_date' => Carbon::create($year, $monthNum, 15)->toDateString(),
            'end_date' => Carbon::create($year, $monthNum, 21)->toDateString(),
        ];

        // الأسبوع الرابع (22-28)
        $weeks[] = [
            'week' => 4,
            'name' => 'الأسبوع الرابع',
            'start_day' => 22,
            'end_day' => 28,
            'start_date' => Carbon::create($year, $monthNum, 22)->toDateString(),
            'end_date' => Carbon::create($year, $monthNum, 28)->toDateString(),
        ];

        // الأسبوع الخامس (29-نهاية الشهر) - إذا كان الشهر يحتوي على 29 يوم أو أكثر
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
): AuthResult
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

        // البحث باسم الحلاق
        if ($search && !empty(trim($search))) {
            $query->whereHas('barber', function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%');
            });
        }

        // الفلترة حسب ID الحلاق
        if ($barberId && is_numeric($barberId)) {
            $query->where('barber_id', $barberId);
        }

        // الفلترة حسب الحالة
        if ($status && in_array($status, ['pending', 'confirmed', 'completed', 'cancelled'])) {
            $query->where('status', $status);
        }

        // الفلترة حسب الفترة (period) مع دعم الأسابيع حسب أيام الشهر
        if ($period) {
            $dateRange = $this->getDateRangeByPeriod($period, $month);
            if ($dateRange) {
                $query->whereBetween('appointment_date', [$dateRange['start'], $dateRange['end']]);
            }
        }

        // الفلترة حسب التاريخ (من) - تتجاوز الفلترة بالفترة إذا وجدت
        if ($dateFrom && $this->isValidDate($dateFrom) && !$period) {
            $query->whereDate('appointment_date', '>=', Carbon::parse($dateFrom)->startOfDay());
        }

        // الفلترة حسب التاريخ (إلى)
        if ($dateTo && $this->isValidDate($dateTo) && !$period) {
            $query->whereDate('appointment_date', '<=', Carbon::parse($dateTo)->endOfDay());
        }

        // إذا تم تحديد تاريخ محدد (بدون فترة)
        if ($dateFrom && !$dateTo && !$period) {
            $query->whereDate('appointment_date', Carbon::parse($dateFrom)->toDateString());
        }

        $appointments = $query->orderBy('appointment_date', 'asc')
            ->orderBy('appointment_time', 'asc')
            ->paginate($perPage, ['*'], 'page', $page);

        // تنسيق البيانات مع إرجاع كائن الزبون كامل
        $formattedAppointments = collect($appointments->items())->map(function ($appointment) {
            $services = $this->getAppointmentServices($appointment);
            $totalPrice = $this->calculateTotalPrice($appointment, $services);
            $totalDuration = $this->calculateTotalDuration($appointment, $services);
            $serviceNames = collect($services)->pluck('name')->implode(' + ');

            // ✅ كائن الحلاق كامل
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
                    // 'email' => $customer->email ?? null,
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

        // إحصائيات الحجوزات مع مراعاة الفلتر
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

        // معلومات الفلتر
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

        // معلومات Pagination
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

        // إذا كان هناك بحث وأوجد حلاقاً
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
    /**
     * إلغاء حجز بواسطة صاحب الصالون
     */
    public function cancelAppointment(User $salonOwner, int $appointmentId, ?string $reason = null): AuthResult
    {
        try {
            return DB::transaction(function () use ($salonOwner, $appointmentId, $reason) {

                // 1. التحقق من أن المستخدم صاحب صالون
                if (!$salonOwner->hasRole('salon_owner')) {
                    return AuthResult::error('هذه الخدمة متاحة لأصحاب الصالونات فقط', null, 403);
                }

                // 2. جلب الصالون الخاص به
                $salon = $salonOwner->ownedSalon;
                if (!$salon) {
                    return AuthResult::error('لا يوجد صالون تابع لك', null, 404);
                }

                // 3. جلب الحجز والتأكد من أنه يتبع هذا الصالون
                $appointment = Appointment::where('id', $appointmentId)
                    ->where('salon_id', $salon->id)
                    ->first();

                if (!$appointment) {
                    return AuthResult::error('الحجز غير موجود أو لا يتبع صالونك', null, 404);
                }

                // 4. التحقق من أن الحجز ليس ملغى بالفعل
                if ($appointment->status === 'cancelled') {
                    return AuthResult::error('هذا الحجز ملغي بالفعل', null, 400);
                }

                // 5. التحقق من أن الحجز ليس مكتملاً
                if ($appointment->status === 'completed') {
                    return AuthResult::error('لا يمكن إلغاء حجز مكتمل', null, 400);
                }

                // 6. إلغاء الحجز
                $appointment->status = 'cancelled';
                $appointment->cancelled_by = 'salon_owner';
                $appointment->save();

                Log::info('Appointment cancelled', [
                    'appointment_id' => $appointmentId,
                    'salon_id' => $salon->id,
                ]);

                return AuthResult::success('تم إلغاء الحجز بنجاح', [
                    'id' => $appointment->id,
                    'status' => $appointment->status,
                ]);

            });
        } catch (\Exception $e) {
            Log::error('Cancel appointment error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء إلغاء الحجز', null, 500);
        }
    }
}
