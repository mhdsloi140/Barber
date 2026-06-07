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

                // 2. تحديث بيانات الصالون
                $this->updateSalonInfo($salon, $data);

                // 3. تحديث الصور (إضافة وحذف)
                $this->updateSalonImages($salon, $data);

                // 4. تحديث أوقات العمل (فترة واحدة فقط مع الحفاظ على القيم غير المرسلة)
                if (isset($data['working_hours']) && !empty($data['working_hours'])) {
                    $this->updateWorkingHours($salon, $data['working_hours']);
                }

                // 5. تحديث كلمة المرور إذا وجدت
                if (isset($data['password']) && !empty($data['password'])) {
                    $this->updatePassword($user, $data['password']);
                }

                // تحميل البيانات المحدثة
                $user->refresh();
                $salon->refresh();

                // جلب تقييمات الصالون بعد التحديث
                $salonRatings = $this->getSalonRatings($salon->id);

                return AuthResult::success('تم تحديث بيانات الصالون بنجاح', [
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
                        // 'notifications_enabled' => (bool) $user->notifications_enabled,
                    ],
                ]);

            });
        } catch (\Exception $e) {
            Log::error('Update salon error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء تحديث البيانات: ' . $e->getMessage(), null, 500);
        }
    }

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

    private function formatUserData(User $user): array
    {
        $roles = $user->getRoleNames()->toArray();
        $primaryRole = !empty($roles) ? $roles[0] : ($user->role ?? 'customer');

        $data = [
            'id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
            'roles' => $roles,
            'is_active' => $user->is_active,
            'avatar' => $user->getAvatarUrlAttribute(),
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
            'notifications_enabled' => (bool) $user->notifications_enabled,
        ];

        return $data;
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
     * تحديث أوقات العمل (فترة واحدة فقط - الحفاظ على القيم غير المرسلة)
     */
    private function updateWorkingHours(Salon $salon, array $workingHours): void
    {
        // جلب الأوقات الحالية أولاً
        $currentHours = $salon->workingHours()
            ->get()
            ->keyBy('day_of_week');

        // معالجة الأوقات الجديدة
        foreach ($workingHours as $hours) {
            $day = $hours['day'];
            $isOpen = $hours['is_open'] ?? false;

            // الحصول على الوقت الحالي لهذا اليوم إذا كان موجوداً
            $currentHour = $currentHours->get($day);

            // دعم كلا التنسيقين: start/end أو shift1_start/shift1_end
            $newStart = $hours['start'] ?? $hours['shift1_start'] ?? null;
            $newEnd = $hours['end'] ?? $hours['shift1_end'] ?? null;

            // 🔴 منطق الحفاظ على القيم:
            // - إذا لم يتم إرسال start، استخدم القيمة الحالية (إذا وجدت)
            // - إذا لم يتم إرسال end، استخدم القيمة الحالية (إذا وجدت)
            // - إذا كان اليوم مغلقاً (is_open = false)، اجعل start و end = null

            if ($isOpen) {
                // إذا كان اليوم مفتوحاً
                if ($newStart === null && $currentHour) {
                    // لم يتم إرسال start، استخدم القيمة الحالية
                    $finalStart = $currentHour->shift1_start;
                } else {
                    $finalStart = $newStart;
                }

                if ($newEnd === null && $currentHour) {
                    // لم يتم إرسال end، استخدم القيمة الحالية
                    $finalEnd = $currentHour->shift1_end;
                } else {
                    $finalEnd = $newEnd;
                }
            } else {
                // إذا كان اليوم مغلقاً
                $finalStart = null;
                $finalEnd = null;
            }

            // تحديث أو إنشاء سجل أوقات العمل
            WorkingHour::updateOrCreate(
                [
                    'workable_type' => Salon::class,
                    'workable_id' => $salon->id,
                    'day_of_week' => $day,
                ],
                [
                    'is_open' => $isOpen,
                    'shift1_start' => $finalStart,
                    'shift1_end' => $finalEnd,
                    // shift2_start, shift2_end, break_start, break_end تبقى null
                ]
            );
        }
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

    /**
     * جلب تقييمات الصالون
     */
    private function getSalonRatings(int $salonId): array
    {
        // جلب جميع التقييمات للصالون (من خلال الحلاقين)
        $barberIds = User::role('barber')
            ->whereHas('salons', function($q) use ($salonId) {
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

        // آخر 5 تقييمات
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
}
