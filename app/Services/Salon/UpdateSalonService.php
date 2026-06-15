<?php
// app/Services/Salon/UpdateSalonService.php

namespace App\Services\Salon;

use App\Models\User;
use App\Models\Salon;
use App\Models\Rating;
use App\Models\Appointment;
use App\Models\WorkingHour;
use App\Services\AuthResult;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Carbon\Carbon;

class UpdateSalonService
{
    /**
     * عرض بيانات الصالون الشخصية مع التقييمات
     */
    public function showSalonProfile(): AuthResult
    {
        try {
            $user = auth()->user();
            $salon = $user->ownedSalon;

            if (!$salon) {
                return AuthResult::error('لا يوجد صالون تابع لك', null, 404);
            }

            // جلب تقييمات الصالون
            $salonRatings = $this->getSalonRatings($salon->id);

            $data = [
                'user' => $this->formatUserData($user),
                'salon' => [
                    'id' => $salon->id,
                    'name' => $salon->name,
                    'address' => $salon->address,
                    'phone' => $salon->phone,
                    'latitude' => $salon->latitude,
                    'longitude' => $salon->longitude,
                    'images' => $salon->getImagesUrlsAttribute(),
                    'working_hours' => $this->getWorkingHoursFormatted($salon),
                    'rating' => $salonRatings['rating'],
                    'statistics' => $salonRatings['statistics'],
                ],
            ];

            return AuthResult::success('تم جلب بيانات الصالون بنجاح', $data);

        } catch (\Exception $e) {
            Log::error('Show salon profile error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب بيانات الصالون', $e->getMessage(), 500);
        }
    }

    /**
     * تحديث بيانات الصالون
     */
    public function updateSalon(array $data): AuthResult
    {
        try {
            return DB::transaction(function () use ($data) {

                $user = auth()->user();
                $salon = $user->ownedSalon;

                if (!$salon) {
                    return AuthResult::error('لا يوجد صالون تابع لك', null, 404);
                }

                // 1. تحديث بيانات المستخدم
                $this->updateUser($user, $data);

                if (isset($data['avatar']) && $data['avatar'] instanceof UploadedFile) {
                    $this->updateAvatar($user, $data['avatar']);
                }

                // 2. تحديث حالة "يعمل كحلاق"
                if (isset($data['works_as_barber'])) {
                    $this->updateWorksAsBarber($user, $salon, (bool) $data['works_as_barber']);
                }

                // 3. تحديث بيانات الصالون
                $this->updateSalonInfo($salon, $data);

                // 4. تحديث الصور (إضافة وحذف)
                $this->updateSalonImages($salon, $data);

                // 5. تحديث أوقات العمل
                if (isset($data['working_hours']) && !empty($data['working_hours'])) {
                    $this->updateWorkingHours($salon, $data['working_hours']);
                }

                // 6. تحديث كلمة المرور إذا وجدت
                if (isset($data['password']) && !empty($data['password'])) {
                    $this->updatePassword($user, $data['password']);
                }

                // 7. تحديث إعدادات الإشعارات
                if (isset($data['notifications_enabled'])) {
                    $user->notifications_enabled = (bool) $data['notifications_enabled'];
                    $user->save();
                    Log::info('Notification settings updated', [
                        'user_id' => $user->id,
                        'enabled' => (bool) $data['notifications_enabled']
                    ]);
                }

                // تحميل البيانات المحدثة
                $user->refresh();
                $salon->refresh();

                // جلب تقييمات الصالون بعد التحديث
                $salonRatings = $this->getSalonRatings($salon->id);

                // تحديث بيانات user format لتشمل الأدوار المحدثة
                $formattedUser = $this->formatUserData($user);

                return AuthResult::success('تم تحديث بيانات الصالون بنجاح', [
                    'user' => $formattedUser,
                    'salon' => [
                        'id' => $salon->id,
                        'name' => $salon->name,
                        'address' => $salon->address,
                        'phone' => $salon->phone,
                        'latitude' => $salon->latitude,
                        'longitude' => $salon->longitude,
                        'images' => $salon->getImagesUrlsAttribute(),
                        'working_hours' => $this->getWorkingHoursFormatted($salon),
                        'rating' => $salonRatings['rating'],
                        'statistics' => $salonRatings['statistics'],
                    ],
                ]);

            });
        } catch (\Exception $e) {
            Log::error('Update salon error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء تحديث البيانات: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * الحصول على تقييمات الصالون مع Paginate
     */
    public function getSalonRatingsPaginated(int $salonId, int $perPage = 10): AuthResult
    {
        try {
            // جلب جميع التقييمات للصالون (من خلال الحلاقين)
            $barberIds = User::role('barber')
                ->whereHas('salons', function ($q) use ($salonId) {
                    $q->where('salon_id', $salonId);
                })
                ->pluck('id')
                ->toArray();

            $ratings = Rating::whereIn('barber_id', $barberIds)
                ->where('is_approved', true)
                ->with('customer')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            $formattedRatings = $ratings->getCollection()->map(function ($rating) {
                return [
                    'id' => $rating->id,
                    'customer_name' => $rating->customer->name,
                    'customer_avatar' => $rating->customer->getAvatarUrlAttribute(),
                    'rating' => $rating->rating,
                    'comment' => $rating->comment,
                    'barber_name' => $rating->barber?->name,
                    'created_at' => $rating->created_at->diffForHumans(),
                    'created_at_raw' => $rating->created_at,
                ];
            });

            $paginationData = [
                'current_page' => $ratings->currentPage(),
                'data' => $formattedRatings,
                'first_page_url' => $ratings->url(1),
                'from' => $ratings->firstItem(),
                'last_page' => $ratings->lastPage(),
                'last_page_url' => $ratings->url($ratings->lastPage()),
                'next_page_url' => $ratings->nextPageUrl(),
                'path' => $ratings->path(),
                'per_page' => $ratings->perPage(),
                'prev_page_url' => $ratings->previousPageUrl(),
                'to' => $ratings->lastItem(),
                'total' => $ratings->total(),
            ];

            return AuthResult::success('تم جلب تقييمات الصالون بنجاح', $paginationData);

        } catch (\Exception $e) {
            Log::error('Get salon ratings paginated error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب التقييمات', null, 500);
        }
    }

    /**
     * جلب الحلاقين مع Paginate
     */
    public function getBarbersPaginated(int $salonId, int $perPage = 10, int $page = 1): AuthResult
    {
        try {
            $salon = Salon::find($salonId);

            if (!$salon) {
                return AuthResult::error('الصالون غير موجود', null, 404);
            }

            $barbers = $salon->barbers()
                ->select('users.id', 'users.name', 'users.phone', 'users.is_active')
                ->paginate($perPage, ['*'], 'page', $page);

            $barbersData = collect($barbers->items())->map(function ($barber) {
                $averageRating = $this->getBarberAverageRating($barber->id);
                $weeklyBookings = $this->getBarberWeeklyBookings($barber->id);
                $totalBookings = $this->getBarberTotalBookings($barber->id);
                $completedBookings = $this->getBarberCompletedBookings($barber->id);

                return [
                    'id' => $barber->id,
                    'name' => $barber->name,
                    'phone' => $barber->phone,
                    'is_active' => $barber->is_active,
                    'avatar' => $barber->getAvatarUrlAttribute(),
                    'rating' => [
                        'average' => $averageRating['average'],
                        'total' => $averageRating['total'],
                        'distribution' => $averageRating['distribution'],
                    ],
                    'statistics' => [
                        'weekly_bookings' => $weeklyBookings,
                        'total_bookings' => $totalBookings,
                        'completed_bookings' => $completedBookings,
                    ],
                ];
            });

            $paginationData = [
                'current_page' => $barbers->currentPage(),
                'data' => $barbersData,
                'first_page_url' => $barbers->url(1),
                'from' => $barbers->firstItem(),
                'last_page' => $barbers->lastPage(),
                'last_page_url' => $barbers->url($barbers->lastPage()),
                'next_page_url' => $barbers->nextPageUrl(),
                'path' => $barbers->path(),
                'per_page' => $barbers->perPage(),
                'prev_page_url' => $barbers->previousPageUrl(),
                'to' => $barbers->lastItem(),
                'total' => $barbers->total(),
            ];

            return AuthResult::success('تم جلب الحلاقين بنجاح', $paginationData);

        } catch (\Exception $e) {
            Log::error('Get barbers paginated error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب الحلاقين', null, 500);
        }
    }

    /**
     * جلب الحجوزات مع Paginate
     */
    public function getSalonAppointmentsPaginated(int $salonId, array $filters = [], int $perPage = 10): AuthResult
    {
        try {
            $query = Appointment::where('salon_id', $salonId)
                ->with(['customer', 'barber', 'service']);

            // تطبيق الفلاتر
            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (!empty($filters['barber_id'])) {
                $query->where('barber_id', $filters['barber_id']);
            }

            if (!empty($filters['date_from'])) {
                $query->whereDate('appointment_date', '>=', $filters['date_from']);
            }

            if (!empty($filters['date_to'])) {
                $query->whereDate('appointment_date', '<=', $filters['date_to']);
            }

            $appointments = $query->orderBy('appointment_date', 'desc')
                ->orderBy('appointment_time', 'desc')
                ->paginate($perPage);

            $formattedAppointments = $appointments->getCollection()->map(function ($appointment) {
                $services = $this->getAppointmentServices($appointment);
                $serviceNames = collect($services)->pluck('name')->implode(' + ');

                return [
                    'id' => $appointment->id,
                    'customer_name' => $appointment->customer->name ?? 'غير معروف',
                    'customer_phone' => $appointment->customer->phone ?? 'غير معروف',
                    'barber_name' => $appointment->barber->name ?? 'غير معروف',
                    'barber_id' => $appointment->barber->id,
                    'services_summary' => $serviceNames,
                    'total_price' => (float) $appointment->total_price,
                    'date' => $this->formatDate($appointment->appointment_date),
                    'time' => $this->formatTime($appointment->appointment_time),
                    'status' => $appointment->status,
                    'created_at' => $this->formatDateTime($appointment->created_at),
                ];
            });

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

            return AuthResult::success('تم جلب الحجوزات بنجاح', $paginationData);

        } catch (\Exception $e) {
            Log::error('Get salon appointments paginated error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب الحجوزات', null, 500);
        }
    }

    /**
     * الحصول على معلومات الحجوزات الإحصائية
     */
    public function getAppointmentsStatistics(int $salonId): AuthResult
    {
        try {
            $stats = [
                'total' => Appointment::where('salon_id', $salonId)->count(),
                'pending' => Appointment::where('salon_id', $salonId)->where('status', 'pending')->count(),
                'confirmed' => Appointment::where('salon_id', $salonId)->where('status', 'confirmed')->count(),
                'completed' => Appointment::where('salon_id', $salonId)->where('status', 'completed')->count(),
                'cancelled' => Appointment::where('salon_id', $salonId)->where('status', 'cancelled')->count(),
                'today' => Appointment::where('salon_id', $salonId)->whereDate('appointment_date', Carbon::today())->count(),
                'this_week' => Appointment::where('salon_id', $salonId)
                    ->whereBetween('appointment_date', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
                    ->count(),
                'this_month' => Appointment::where('salon_id', $salonId)
                    ->whereBetween('appointment_date', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])
                    ->count(),
            ];

            return AuthResult::success('تم جلب إحصائيات الحجوزات بنجاح', $stats);

        } catch (\Exception $e) {
            Log::error('Get appointments statistics error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب الإحصائيات', null, 500);
        }
    }

    // ===================== دوال مساعدة للحلاقين =====================

    /**
     * جلب متوسط تقييم الحلاق
     */
    private function getBarberAverageRating(int $barberId): array
    {
        $ratings = Rating::where('barber_id', $barberId)
            ->where('is_approved', true)
            ->get();

        $total = $ratings->count();
        $average = $total > 0 ? round($ratings->avg('rating'), 1) : 0;

        $distribution = [
            5 => $ratings->where('rating', 5)->count(),
            4 => $ratings->where('rating', 4)->count(),
            3 => $ratings->where('rating', 3)->count(),
            2 => $ratings->where('rating', 2)->count(),
            1 => $ratings->where('rating', 1)->count(),
        ];

        return [
            'average' => $average,
            'total' => $total,
            'distribution' => $distribution,
        ];
    }

    /**
     * جلب عدد حجوزات الحلاق في الأسبوع الحالي
     */
    private function getBarberWeeklyBookings(int $barberId): int
    {
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();

        return Appointment::where('barber_id', $barberId)
            ->whereBetween('appointment_date', [$startOfWeek, $endOfWeek])
            ->count();
    }

    /**
     * جلب عدد حجوزات الحلاق الإجمالي
     */
    private function getBarberTotalBookings(int $barberId): int
    {
        return Appointment::where('barber_id', $barberId)->count();
    }

    /**
     * جلب عدد حجوزات الحلاق المكتملة
     */
    private function getBarberCompletedBookings(int $barberId): int
    {
        return Appointment::where('barber_id', $barberId)
            ->where('status', 'completed')
            ->count();
    }

    // ===================== دوال مساعدة أساسية =====================

    /**
     * تحديث الصورة الشخصية
     */
    private function updateAvatar(User $user, UploadedFile $avatar): void
    {
        try {
            // حذف الصورة القديمة
            $user->clearMediaCollection('avatar');

            // إضافة الصورة الجديدة
            $user->addMedia($avatar)
                ->usingFileName('avatar_' . time() . '_' . uniqid() . '.' . $avatar->getClientOriginalExtension())
                ->toMediaCollection('avatar');

            Log::info('Avatar updated for user', ['user_id' => $user->id]);
        } catch (\Exception $e) {
            Log::error('Avatar update failed: ' . $e->getMessage());
        }
    }

    /**
     * تنسيق بيانات المستخدم
     */
    private function formatUserData(User $user): array
    {
        $roles = $user->getRoleNames()->toArray();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
            'roles' => $roles,
            'is_active' => $user->is_active,
            'avatar' => $user->getAvatarUrlAttribute(),
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
            'notifications_enabled' => (bool) $user->notifications_enabled,
            'works_as_barber' => $user->hasRole('barber'),
        ];
    }

    /**
     * تحديث بيانات المستخدم
     */
    private function updateUser(User $user, array $data): void
    {
        $userData = [];

        if (isset($data['name'])) {
            $userData['name'] = $data['name'];
        }
        if (isset($data['phone'])) {
            if (User::where('phone', $data['phone'])->where('id', '!=', $user->id)->exists()) {
                Log::warning('Phone number already exists', ['phone' => $data['phone']]);
            } else {
                $userData['phone'] = $data['phone'];
            }
        }

        if (!empty($userData)) {
            $user->update($userData);
        }
    }

    /**
     * تحديث حالة "يعمل كحلاق"
     */
    private function updateWorksAsBarber(User $user, Salon $salon, bool $worksAsBarber): void
    {
        if ($worksAsBarber) {
            // إضافة دور الحلاق إذا لم يكن موجوداً
            if (!$user->hasRole('barber')) {
                $user->assignRole('barber');
                Log::info('Barber role assigned to salon owner', ['user_id' => $user->id]);
            }

            // إضافة العلاقة مع الصالون إذا لم تكن موجودة
            $exists = $user->salons()->where('salon_id', $salon->id)->exists();
            if (!$exists) {
                $user->salons()->attach($salon->id, [
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                Log::info('Salon owner added as barber to his salon', [
                    'user_id' => $user->id,
                    'salon_id' => $salon->id,
                ]);
            }
        } else {
            // إزالة دور الحلاق إذا كان موجوداً
            if ($user->hasRole('barber')) {
                $user->removeRole('barber');
                Log::info('Barber role removed from salon owner', ['user_id' => $user->id]);
            }

            // إزالة العلاقة مع الصالون
            $user->salons()->detach($salon->id);
            Log::info('Salon owner removed as barber from his salon', [
                'user_id' => $user->id,
                'salon_id' => $salon->id,
            ]);
        }
    }

    /**
     * تحديث بيانات الصالون
     */
    private function updateSalonInfo(Salon $salon, array $data): void
    {
        $salonData = [];

        if (isset($data['salon_name'])) {
            $salonData['name'] = $data['salon_name'];
        }
        if (isset($data['salon_address'])) {
            $salonData['address'] = $data['salon_address'];
        }
        if (isset($data['salon_phone'])) {
            $salonData['phone'] = $data['salon_phone'];
        }
        if (isset($data['latitude'])) {
            $salonData['latitude'] = $data['latitude'];
        }
        if (isset($data['longitude'])) {
            $salonData['longitude'] = $data['longitude'];
        }
        if (isset($data['description'])) {
            $salonData['description'] = $data['description'];
        }

        if (!empty($salonData)) {
            $salon->update($salonData);
        }
    }

    /**
     * تحديث صور الصالون (إضافة وحذف)
     */
    private function updateSalonImages(Salon $salon, array $data): void
    {
        // حذف الصور المحددة
        if (isset($data['delete_image_ids']) && is_array($data['delete_image_ids'])) {
            foreach ($data['delete_image_ids'] as $imageId) {
                $media = Media::find($imageId);
                if ($media && $media->model_id == $salon->id && $media->model_type == Salon::class) {
                    $media->delete();
                }
            }
        }

        // إضافة صور جديدة
        if (isset($data['new_images']) && is_array($data['new_images'])) {
            foreach ($data['new_images'] as $image) {
                if ($image instanceof UploadedFile) {
                    try {
                        $salon->addMedia($image)
                            ->usingFileName('salon_' . time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension())
                            ->toMediaCollection('salon_images');
                    } catch (\Exception $e) {
                        Log::error('Image upload failed: ' . $e->getMessage());
                    }
                }
            }
        }
    }

    /**
     * تحديث أوقات العمل (يحذف جميع الأوقات القديمة ويحفظ الأوقات الجديدة فقط)
     */
    private function updateWorkingHours(Salon $salon, array $workingHours): void
    {
        // 1. حذف جميع أوقات العمل الحالية للصالون
        WorkingHour::where('workable_type', Salon::class)
            ->where('workable_id', $salon->id)
            ->delete();

        // 2. إضافة أوقات العمل الجديدة (الأيام المرسلة فقط)
        foreach ($workingHours as $hours) {
            $day = $hours['day'];
            $isOpen = $hours['is_open'] ?? false;

            // دعم كلا التنسيقين: start/end أو shift1_start/shift1_end
            $start = $hours['start'] ?? $hours['shift1_start'] ?? null;
            $end = $hours['end'] ?? $hours['shift1_end'] ?? null;

            WorkingHour::create([
                'workable_type' => Salon::class,
                'workable_id' => $salon->id,
                'day_of_week' => $day,
                'is_open' => $isOpen,
                'shift1_start' => $isOpen ? $start : null,
                'shift1_end' => $isOpen ? $end : null,
            ]);
        }

        Log::info('Working hours updated (deleted all, saved new)', [
            'salon_id' => $salon->id,
            'days_saved' => array_column($workingHours, 'day'),
        ]);
    }

    /**
     * تحديث كلمة المرور
     */
    private function updatePassword(User $user, string $password): void
    {
        $user->password = Hash::make($password);
        $user->save();

        // تسجيل الخروج من جميع الأجهزة بعد تغيير كلمة المرور
        $user->tokens()->delete();
    }

 
    private function getSalonRatings(int $salonId): array
    {
        // جلب جميع التقييمات للصالون (من خلال الحلاقين)
        $barberIds = User::role('barber')
            ->whereHas('salons', function ($q) use ($salonId) {
                $q->where('salon_id', $salonId);
            })
            ->pluck('id')
            ->toArray();

        $ratings = Rating::whereIn('barber_id', $barberIds)
            ->where('is_approved', true)
            ->get();

        $totalRatings = $ratings->count();
        $averageRating = $totalRatings > 0 ? round($ratings->avg('rating'), 1) : 0;

        // توزيع التقييمات
        $distribution = [
            5 => $ratings->where('rating', 5)->count(),
            4 => $ratings->where('rating', 4)->count(),
            3 => $ratings->where('rating', 3)->count(),
            2 => $ratings->where('rating', 2)->count(),
            1 => $ratings->where('rating', 1)->count(),
        ];

        // آخر 5 تقييمات (للبروفايل)
        $recentRatings = Rating::whereIn('barber_id', $barberIds)
            ->where('is_approved', true)
            ->with('customer')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($rating) {
                return [
                    'id' => $rating->id,
                    'customer_name' => $rating->customer->name,
                    'rating' => $rating->rating,
                    'comment' => $rating->comment,
                    'barber_name' => $rating->barber?->name,
                    'created_at' => $rating->created_at->diffForHumans(),
                ];
            });

        // إحصائيات الحجوزات
        $totalAppointments = Appointment::where('salon_id', $salonId)->count();
        $completedAppointments = Appointment::where('salon_id', $salonId)
            ->where('status', 'completed')
            ->count();
        $weeklyAppointments = Appointment::where('salon_id', $salonId)
            ->whereBetween('appointment_date', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
            ->count();

        return [
            'rating' => [
                'average' => $averageRating,
                'total' => $totalRatings,
                'distribution' => $distribution,
                'recent' => $recentRatings,
            ],
            'statistics' => [
                'total_appointments' => $totalAppointments,
                'completed_appointments' => $completedAppointments,
                'weekly_appointments' => $weeklyAppointments,
            ],
        ];
    }

    /**
     * تنسيق أوقات العمل للعرض (فترة واحدة فقط)
     */
    private function getWorkingHoursFormatted(Salon $salon): array
    {
        $daysInArabic = [
            'sunday' => 'الأحد',
            'monday' => 'الإثنين',
            'tuesday' => 'الثلاثاء',
            'wednesday' => 'الأربعاء',
            'thursday' => 'الخميس',
            'friday' => 'الجمعة',
            'saturday' => 'السبت',
        ];

        $workingHours = $salon->workingHours()
            ->orderByRaw("FIELD(day_of_week, 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday')")
            ->get();

        $result = [];
        foreach ($workingHours as $hour) {
            $result[] = [
                'day' => $hour->day_of_week,
                'day_ar' => $daysInArabic[$hour->day_of_week],
                'is_open' => (bool) $hour->is_open,
                'start' => $hour->shift1_start,
                'end' => $hour->shift1_end,
                'time_range' => ($hour->is_open && $hour->shift1_start && $hour->shift1_end)
                    ? $hour->shift1_start . ' - ' . $hour->shift1_end
                    : null,
            ];
        }
        return $result;
    }

    /**
     * تنسيق التاريخ
     */
    private function formatDate($date): ?string
    {
        if (!$date) return null;
        return Carbon::parse($date)->format('Y-m-d');
    }

    /**
     * تنسيق الوقت
     */
    private function formatTime($time): ?string
    {
        if (!$time) return null;
        return Carbon::parse($time)->format('H:i');
    }

    /**
     * تنسيق التاريخ والوقت
     */
    private function formatDateTime($datetime): ?string
    {
        if (!$datetime) return null;
        return Carbon::parse($datetime)->format('Y-m-d H:i:s');
    }

    /**
     * الحصول على خدمات الحجز
     */
    private function getAppointmentServices(Appointment $appointment): array
    {
        if ($appointment->services_details) {
            $services = is_array($appointment->services_details)
                ? $appointment->services_details
                : json_decode($appointment->services_details, true);

            if (is_array($services) && !empty($services)) {
                return $services;
            }
        }
        return [];
    }
}
