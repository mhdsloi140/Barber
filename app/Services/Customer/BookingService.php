<?php
// app/Services/Customer/BookingService.php

namespace App\Services\Customer;

use App\Models\User;
use App\Models\Salon;
use App\Models\Appointment;
use App\Models\BarberService;
use App\Models\Rating;
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
            $services = json_decode($appointment->services_details, true);
            if (is_array($services) && !empty($services)) {
                return $services;
            }
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

        try {
            $date = $appointment->appointment_date;
            $time = $appointment->appointment_time;

            if (is_string($time)) {
                $time = substr($time, 0, 5);
            }

            $appointmentDateTime = Carbon::parse($date . ' ' . $time);
            return $appointmentDateTime->isFuture();
        } catch (\Exception $e) {
            Log::error('Can cancel appointment error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * التحقق من إمكانية تقييم الحجز
     */
    private function canRateAppointment(Appointment $appointment): bool
    {
        if ($appointment->status !== 'completed') {
            return false;
        }

        $existingRating = Rating::where('appointment_id', $appointment->id)
            ->where('customer_id', $appointment->customer_id)
            ->exists();

        return !$existingRating;
    }

    /**
     * حساب تقييم الحلاق مع التوزيع
     */
    private function getBarberRatingData(User $barber): array
    {
        $ratings = Rating::where('barber_id', $barber->id)
            ->where('is_approved', true)
            ->get();

        $totalRatings = $ratings->count();

        if ($totalRatings === 0) {
            return [
                'average' => 0,
                'total' => 0,
                'distribution' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0],
                'recent' => []
            ];
        }

        $distribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        foreach ($ratings as $rating) {
            $ratingValue = (int) round($rating->rating);
            if (isset($distribution[$ratingValue])) {
                $distribution[$ratingValue]++;
            }
        }

        $average = round($ratings->avg('rating'), 1);

        $recent = $ratings->sortByDesc('created_at')->take(3)->map(fn($rating) => [
            'id' => $rating->id,
            'rating' => $rating->rating,
            'comment' => $rating->comment,
            'customer_name' => $rating->customer->name ?? null,
            'created_at' => $rating->created_at->diffForHumans(),
        ]);

        return [
            'average' => $average,
            'total' => $totalRatings,
            'distribution' => $distribution,
            'recent' => $recent,
        ];
    }

    // ===================== دوال جلب الحجوزات =====================

    /**
     * جلب الحجوزات النشطة (المؤكدة)
     */
    public function getActiveAppointments(User $customer, int $perPage = 5): AuthResult
    {
        try {
            if (!$customer->hasRole('customer')) {
                return AuthResult::error('هذه الخدمة متاحة للزبائن فقط', null, 403);
            }

            $appointments = Appointment::where('customer_id', $customer->id)
                ->whereIn('status', ['confirmed','pending'])
                ->with(['barber', 'salon'])
                ->orderBy('appointment_date', 'asc')
                ->orderBy('appointment_time', 'asc')
                ->paginate($perPage);

            $items = collect($appointments->items())->map(function ($appointment) {
                return [
                    'id' => $appointment->id,
                    'barber' => [
                        'id' => $appointment->barber->id,
                        'name' => $appointment->barber->name,
                        'phone' => $appointment->barber->phone,
                        'avatar' => $appointment->barber->getAvatarUrlAttribute(),
                        'is_active' => $appointment->barber->is_active,
                        'rating' => $this->getBarberRatingData($appointment->barber),
                    ],
                    'salon' => $this->formatSalonData($appointment->salon),
                    'services' => $this->getAppointmentServices($appointment),
                    'total_price' => (float) $appointment->total_price,
                    'date' => $this->formatDate($appointment->appointment_date),
                    'time' => $this->formatTime($appointment->appointment_time),
                    'end_time' => $this->formatTime($appointment->end_time),
                    'status' => $appointment->status,
                    'status_text' => $this->getStatusText($appointment->status),
                    'can_cancel' => $this->canCancelAppointment($appointment),
                    'created_at' => $appointment->created_at,
                ];
            });

            return AuthResult::success('تم جلب الحجوزات النشطة بنجاح', [
                'appointments' => $items,
                'pagination' => [
                    'current_page' => $appointments->currentPage(),
                    'last_page' => $appointments->lastPage(),
                    'per_page' => $appointments->perPage(),
                    'total' => $appointments->total(),
                    'next_page_url' => $appointments->nextPageUrl(),
                    'prev_page_url' => $appointments->previousPageUrl(),
                    'has_more_pages' => $appointments->hasMorePages(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get active appointments error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب الحجوزات النشطة', $e->getMessage(), 500);
        }
    }

    /**
     * جلب الحجوزات قيد الانتظار فقط
     */
    public function getPendingAppointments(User $customer, int $perPage = 2): AuthResult
    {
        try {
            if (!$customer->hasRole('customer')) {
                return AuthResult::error('هذه الخدمة متاحة للزبائن فقط', null, 403);
            }

            $appointments = Appointment::where('customer_id', $customer->id)
                ->where('status', 'pending')
                ->with(['barber', 'salon'])
                ->orderBy('appointment_date', 'asc')
                ->orderBy('appointment_time', 'asc')
                ->paginate($perPage);

            $items = collect($appointments->items())->map(function ($appointment) {
                return [
                    'id' => $appointment->id,
                    'barber' => [
                        'id' => $appointment->barber->id,
                        'name' => $appointment->barber->name,
                        'phone' => $appointment->barber->phone,
                        'avatar' => $appointment->barber->getAvatarUrlAttribute(),
                        'is_active' => $appointment->barber->is_active,
                        'rating' => $this->getBarberRatingData($appointment->barber),
                    ],
                    'salon' => $this->formatSalonData($appointment->salon),
                    'services' => $this->getAppointmentServices($appointment),
                    'total_price' => (float) $appointment->total_price,
                    'date' => $this->formatDate($appointment->appointment_date),
                    'time' => $this->formatTime($appointment->appointment_time),
                    'end_time' => $this->formatTime($appointment->end_time),
                    'status' => $appointment->status,
                    'status_text' => $this->getStatusText($appointment->status),
                    'can_cancel' => $this->canCancelAppointment($appointment),
                    'created_at' => $appointment->created_at,
                ];
            });

            return AuthResult::success('تم جلب الحجوزات قيد الانتظار بنجاح', [
                'appointments' => $items,
                'pagination' => [
                    'current_page' => $appointments->currentPage(),
                    'last_page' => $appointments->lastPage(),
                    'per_page' => $appointments->perPage(),
                    'total' => $appointments->total(),
                    'next_page_url' => $appointments->nextPageUrl(),
                    'prev_page_url' => $appointments->previousPageUrl(),
                    'has_more_pages' => $appointments->hasMorePages(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get pending appointments error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب الحجوزات قيد الانتظار', $e->getMessage(), 500);
        }
    }

    /**
     * جلب الحجوزات المؤكدة فقط
     */
    public function getConfirmedAppointments(User $customer, int $perPage = 5): AuthResult
    {
        try {
            if (!$customer->hasRole('customer')) {
                return AuthResult::error('هذه الخدمة متاحة للزبائن فقط', null, 403);
            }

            $appointments = Appointment::where('customer_id', $customer->id)
                ->whereIn('status', ['confirmed','pinding'])
                ->with(['barber', 'salon'])
                ->orderBy('appointment_date', 'asc')
                ->orderBy('appointment_time', 'asc')
                ->paginate($perPage);

            $items = collect($appointments->items())->map(function ($appointment) {
                return [
                    'id' => $appointment->id,
                    'barber' => [
                        'id' => $appointment->barber->id,
                        'name' => $appointment->barber->name,
                        'phone' => $appointment->barber->phone,
                        'avatar' => $appointment->barber->getAvatarUrlAttribute(),
                        'is_active' => $appointment->barber->is_active,
                        'rating' => $this->getBarberRatingData($appointment->barber),
                    ],
                    'salon' => $this->formatSalonData($appointment->salon),
                    'services' => $this->getAppointmentServices($appointment),
                    'total_price' => (float) $appointment->total_price,
                    'date' => $this->formatDate($appointment->appointment_date),
                    'time' => $this->formatTime($appointment->appointment_time),
                    'end_time' => $this->formatTime($appointment->end_time),
                    'status' => $appointment->status,
                    'status_text' => $this->getStatusText($appointment->status),
                    'can_cancel' => $this->canCancelAppointment($appointment),
                    'created_at' => $appointment->created_at,
                ];
            });

            return AuthResult::success('تم جلب الحجوزات المؤكدة بنجاح', [
                'appointments' => $items,
                'pagination' => [
                    'current_page' => $appointments->currentPage(),
                    'last_page' => $appointments->lastPage(),
                    'per_page' => $appointments->perPage(),
                    'total' => $appointments->total(),
                    'next_page_url' => $appointments->nextPageUrl(),
                    'prev_page_url' => $appointments->previousPageUrl(),
                    'has_more_pages' => $appointments->hasMorePages(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get confirmed appointments error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب الحجوزات المؤكدة', $e->getMessage(), 500);
        }
    }

    /**
     * جلب الحجوزات المكتملة فقط
     */
    public function getCompletedAppointments(User $customer, int $perPage = 5): AuthResult
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
                ->paginate($perPage);

            $items = collect($appointments->items())->map(function ($appointment) {
                return [
                    'id' => $appointment->id,
                    'barber' => [
                        'id' => $appointment->barber->id,
                        'name' => $appointment->barber->name,
                        'phone' => $appointment->barber->phone,
                        'avatar' => $appointment->barber->getAvatarUrlAttribute(),
                        'is_active' => $appointment->barber->is_active,
                        'rating' => $this->getBarberRatingData($appointment->barber),
                    ],
                    'salon' => $this->formatSalonData($appointment->salon),
                    'services' => $this->getAppointmentServices($appointment),
                    'total_price' => (float) $appointment->total_price,
                    'date' => $this->formatDate($appointment->appointment_date),
                    'time' => $this->formatTime($appointment->appointment_time),
                    'end_time' => $this->formatTime($appointment->end_time),
                    'status' => $appointment->status,
                    'status_text' => $this->getStatusText($appointment->status),
                    'can_rate' => $this->canRateAppointment($appointment),
                    'completed_at' => $appointment->completed_at,
                    'created_at' => $appointment->created_at,
                ];
            });

            return AuthResult::success('تم جلب الحجوزات المكتملة بنجاح', [
                'appointments' => $items,
                'pagination' => [
                    'current_page' => $appointments->currentPage(),
                    'last_page' => $appointments->lastPage(),
                    'per_page' => $appointments->perPage(),
                    'total' => $appointments->total(),
                    'next_page_url' => $appointments->nextPageUrl(),
                    'prev_page_url' => $appointments->previousPageUrl(),
                    'has_more_pages' => $appointments->hasMorePages(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get completed appointments error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب الحجوزات المكتملة', $e->getMessage(), 500);
        }
    }

    /**
     * جلب الحجوزات الملغية فقط
     */
    public function getCancelledAppointments(User $customer, int $perPage = 5): AuthResult
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
                ->paginate($perPage);

            $items = collect($appointments->items())->map(function ($appointment) {
                return [
                    'id' => $appointment->id,
                    'barber' => [
                        'id' => $appointment->barber->id,
                        'name' => $appointment->barber->name,
                        'phone' => $appointment->barber->phone,
                        'avatar' => $appointment->barber->getAvatarUrlAttribute(),
                        'is_active' => $appointment->barber->is_active,
                        'rating' => $this->getBarberRatingData($appointment->barber),
                    ],
                    'salon' => $this->formatSalonData($appointment->salon),
                    'services' => $this->getAppointmentServices($appointment),
                    'total_price' => (float) $appointment->total_price,
                    'date' => $this->formatDate($appointment->appointment_date),
                    'time' => $this->formatTime($appointment->appointment_time),
                    'end_time' => $this->formatTime($appointment->end_time),
                    'status' => $appointment->status,
                    'status_text' => $this->getStatusText($appointment->status),
                    'cancelled_at' => $appointment->cancelled_at,
                    'cancellation_reason' => $appointment->cancellation_reason,
                    'created_at' => $appointment->created_at,
                ];
            });

            return AuthResult::success('تم جلب الحجوزات الملغية بنجاح', [
                'appointments' => $items,
                'pagination' => [
                    'current_page' => $appointments->currentPage(),
                    'last_page' => $appointments->lastPage(),
                    'per_page' => $appointments->perPage(),
                    'total' => $appointments->total(),
                    'next_page_url' => $appointments->nextPageUrl(),
                    'prev_page_url' => $appointments->previousPageUrl(),
                    'has_more_pages' => $appointments->hasMorePages(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get cancelled appointments error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب الحجوزات الملغية', $e->getMessage(), 500);
        }
    }

    /**
     * جلب جميع حجوزات الزبون (مع فلترة حسب الحالة)
     */
    public function getCustomerAppointments(User $customer, ?string $status = null, int $perPage = 5): AuthResult
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
                ->paginate($perPage);

            $items = collect($appointments->items())->map(function ($appointment) {
                return [
                    'id' => $appointment->id,
                    'barber' => [
                        'id' => $appointment->barber->id,
                        'name' => $appointment->barber->name,
                        'phone' => $appointment->barber->phone,
                        'avatar' => $appointment->barber->getAvatarUrlAttribute(),
                        'rating' => $this->getBarberRatingData($appointment->barber),
                    ],
                    'salon' => $this->formatSalonData($appointment->salon),
                    'services' => $this->getAppointmentServices($appointment),
                    'total_price' => (float) $appointment->total_price,
                    'date' => $this->formatDate($appointment->appointment_date),
                    'time' => $this->formatTime($appointment->appointment_time),
                    'end_time' => $this->formatTime($appointment->end_time),
                    'status' => $appointment->status,
                    'status_text' => $this->getStatusText($appointment->status),
                    'can_cancel' => $this->canCancelAppointment($appointment),
                    'can_rate' => $this->canRateAppointment($appointment),
                    'created_at' => $appointment->created_at,
                ];
            });

            return AuthResult::success('تم جلب الحجوزات بنجاح', [
                'appointments' => $items,
                'pagination' => [
                    'current_page' => $appointments->currentPage(),
                    'last_page' => $appointments->lastPage(),
                    'per_page' => $appointments->perPage(),
                    'total' => $appointments->total(),
                    'next_page_url' => $appointments->nextPageUrl(),
                    'prev_page_url' => $appointments->previousPageUrl(),
                    'has_more_pages' => $appointments->hasMorePages(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get customer appointments error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب الحجوزات', $e->getMessage(), 500);
        }
    }

    /**
     * جلب تفاصيل حجز محدد
     */
    public function getAppointmentDetails(User $customer, int $appointmentId): AuthResult
    {
        try {
            if (!$customer->hasRole('customer')) {
                return AuthResult::error('هذه الخدمة متاحة للزبائن فقط', null, 403);
            }

            $appointment = Appointment::where('customer_id', $customer->id)
                ->where('id', $appointmentId)
                ->with(['barber', 'salon'])
                ->first();

            if (!$appointment) {
                return AuthResult::error('الحجز غير موجود', null, 404);
            }

            $services = $this->getAppointmentServices($appointment);
            $totalDuration = $appointment->duration_minutes;
            $totalPrice = (float) $appointment->total_price;

            $data = [
                'id' => $appointment->id,
                'status' => $appointment->status,
                'status_text' => $this->getStatusText($appointment->status),
                'can_cancel' => $this->canCancelAppointment($appointment),
                'can_rate' => $this->canRateAppointment($appointment),
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                ],
                'barber' => [
                    'id' => $appointment->barber->id,
                    'name' => $appointment->barber->name,
                    'phone' => $appointment->barber->phone,
                    'avatar' => $appointment->barber->getAvatarUrlAttribute(),
                    'rating' => $this->getBarberRatingData($appointment->barber),
                ],
                'salon' => $this->formatSalonData($appointment->salon),
                'services' => $services,
                'services_summary' => implode(' + ', array_column($services, 'name')),
                'total_duration' => $totalDuration,
                'total_price' => $totalPrice,
                'date' => $this->formatDate($appointment->appointment_date),
                'day_name' => $this->getArabicDayName(Carbon::parse($appointment->appointment_date)->format('l')),
                'time' => $this->formatTime($appointment->appointment_time),
                'end_time' => $this->formatTime($appointment->end_time),
                'created_at' => $appointment->created_at,
                'updated_at' => $appointment->updated_at,
                'cancelled_at' => $appointment->cancelled_at,
                'cancellation_reason' => $appointment->cancellation_reason,
                'completed_at' => $appointment->completed_at,
            ];

            return AuthResult::success('تم جلب تفاصيل الحجز بنجاح', $data);
        } catch (\Exception $e) {
            Log::error('Get appointment details error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب تفاصيل الحجز', $e->getMessage(), 500);
        }
    }

    // ===================== دوال إنشاء وتعديل الحجوزات =====================

    /**
     * إنشاء حجز جديد
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

                $conflictingBooking = Appointment::where('barber_id', $data['barber_id'])
                    ->whereDate('appointment_date', $appointmentDate)
                    ->whereIn('status', ['pending', 'confirmed'])
                    ->where(function ($query) use ($appointmentTimeFormatted, $endTime) {
                        $query->where('appointment_time', '<', $endTime)
                            ->where('end_time', '>', $appointmentTimeFormatted);
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
                    $notificationService->notifyNewAppointmentToBarber($salon, $appointment);
                    $notificationService->notifySalonOwnerAboutNewAppointment($salon, $appointment);
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
                        'date' => $this->formatDate($appointment->appointment_date),
                        'day' => $data['day'] ?? null,
                        'time' => $this->formatTime($appointment->appointment_time),
                        'end_time' => $this->formatTime($appointment->end_time),
                        'status' => $appointment->status,
                        'notes' => $appointment->notes,
                        'created_at' => $appointment->created_at,
                    ],
                ], 201);
            });
        } catch (\Exception $e) {
            Log::error('Store booking error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء إنشاء الحجز: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * إلغاء حجز
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
                    return AuthResult::error("لا يمكن إلغاء هذا الحجز، حالته الحالية: {$appointment->status}", null, 400);
                }

                $appointment->status = 'cancelled';
                $appointment->cancelled_at = now();
                $appointment->cancellation_reason = $reason;
                $appointment->save();

                return AuthResult::success('تم إلغاء الحجز بنجاح', [
                    'id' => $appointment->id,
                    'status' => $appointment->status,
                    'status_text' => $this->getStatusText($appointment->status),
                    'cancelled_at' => $appointment->cancelled_at,
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Cancel appointment error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء إلغاء الحجز: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * تحديث حجز
     */
    public function updateAppointment(User $customer, int $appointmentId, array $data): AuthResult
    {
        try {
            return DB::transaction(function () use ($customer, $appointmentId, $data) {
                $appointment = Appointment::where('customer_id', $customer->id)
                    ->where('id', $appointmentId)
                    ->first();

                if (!$appointment) {
                    return AuthResult::error('الحجز غير موجود', null, 404);
                }

                if (!in_array($appointment->status, ['pending', 'confirmed'])) {
                    return AuthResult::error('لا يمكن تعديل هذا الحجز', null, 400);
                }

                $newTime = $data['time'] ?? ($appointment->appointment_time instanceof Carbon
                    ? $appointment->appointment_time->format('H:i')
                    : substr($appointment->appointment_time, 0, 5));

                $newDate = $data['appointment_date'] ?? ($appointment->appointment_date instanceof Carbon
                    ? $appointment->appointment_date->format('Y-m-d')
                    : $appointment->appointment_date);

                $duration = $appointment->duration_minutes;
                $newTimeFormatted = Carbon::parse($newTime)->format('H:i:s');
                $newEndTime = Carbon::parse($newTime)->addMinutes($duration)->format('H:i:s');

                $conflictingBooking = Appointment::where('barber_id', $appointment->barber_id)
                    ->where('id', '!=', $appointment->id)
                    ->whereDate('appointment_date', $newDate)
                    ->whereIn('status', ['pending', 'confirmed'])
                    ->where(function ($query) use ($newTimeFormatted, $newEndTime) {
                        $query->where('appointment_time', '<', $newEndTime)
                            ->where('end_time', '>', $newTimeFormatted);
                    })
                    ->exists();

                if ($conflictingBooking) {
                    return AuthResult::error('الوقت المحدد غير متاح، يوجد حجز آخر في نفس الوقت', null, 400);
                }

                if (isset($data['time'])) {
                    $appointment->appointment_time = $newTimeFormatted;
                    $appointment->end_time = $newEndTime;
                }

                if (isset($data['appointment_date'])) {
                    $appointment->appointment_date = $newDate;
                }

                if (isset($data['notes'])) {
                    $appointment->notes = $data['notes'];
                }

                $appointment->save();

                try {
                    $notificationService = app(FirebaseNotificationService::class);
                    $notificationService->notifyAppointmentUpdatedToBarber($appointment);
                } catch (\Exception $e) {
                    Log::error('Failed to send update notification: ' . $e->getMessage());
                }

                return AuthResult::success('تم تعديل الحجز بنجاح', [
                    'id' => $appointment->id,
                    'status' => $appointment->status,
                    'appointment_date' => $this->formatDate($appointment->appointment_date),
                    'appointment_time' => $this->formatTime($appointment->appointment_time),
                    'end_time' => $this->formatTime($appointment->end_time),
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Update appointment error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء تعديل الحجز: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * جلب الأوقات المتاحة للحلاق
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
                return AuthResult::error('لا توجد أوقات متاحة في هذا اليوم', null, 400);
            }

            return AuthResult::success('تم جلب الأوقات المتاحة بنجاح', $availableTimes);
        } catch (\Exception $e) {
            Log::error('Get available times error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب الأوقات المتاحة', $e->getMessage(), 500);
        }
    }

    // ===================== دوال مساعدة =====================

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
}
