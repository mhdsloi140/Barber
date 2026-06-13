<?php
// app/Services/BarberService.php

namespace App\Services;

use App\Models\User;
use App\Models\WorkingHour;
use App\Models\Rating;
use App\Models\Appointment;
use App\Services\AuthResult;
use App\Services\ultraMessage\UltraMsgService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class BarberService
{
    protected UltraMsgService $ultraMsg;

    public function __construct(UltraMsgService $ultraMsg)
    {
        $this->ultraMsg = $ultraMsg;
    }

    /**
     * إنشاء كلمة مرور بسيطة (6 أرقام)
     */
    protected function generateSimplePassword(): string
    {
        return (string) rand(100000, 999999);
    }

    /**
     * إضافة حلاق جديد
     */
    public function addBarber(array $data, User $salonOwner): AuthResult
    {
        try {
            return DB::transaction(function () use ($data, $salonOwner) {

                $salon = $salonOwner->ownedSalon;
                if (!$salon) {
                    return AuthResult::error('لا يوجد صالون تابع لك', null, 404);
                }

                // إنشاء كلمة مرور عشوائية
                $plainPassword = $this->generateSimplePassword();
                $hashedPassword = Hash::make($plainPassword);

                $barber = User::create([
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                    'password' => $hashedPassword,
                    'role' => 'barber',
                    'is_active' => true,
                ]);

                $barber->assignRole('barber');

                $barber->salons()->attach($salon->id, [
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // إرسال بيانات الدخول عبر WhatsApp
                $this->ultraMsg->sendCredentials($barber, $salon, $plainPassword, 'barber');

                return AuthResult::success(
                    'تم اضافة الحلاق بنجاح',
                    [
                        'id' => $barber->id,
                        'name' => $barber->name,
                        'phone' => $barber->phone,
                    ],
                    201
                );

            });
        } catch (\Exception $e) {
            Log::error('Add barber error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء إضافة الحلاق: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * جلب كل الحلاقين التابعين لصاحب الصالون مع تقييماتهم وإحصائياتهم
     */
  public function getBarbers(User $salonOwner): AuthResult
{
    try {
        $salon = $salonOwner->ownedSalon;

        if (!$salon) {
            return AuthResult::error('لا يوجد صالون تابع لك', null, 404);
        }

        $barbers = $salon->barbers()
            ->select('users.id', 'users.name', 'users.phone', 'users.is_active')
            ->get();

        $barbersData = $barbers->map(function($barber) {
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

        return AuthResult::success('تم جلب الحلاقين بنجاح', $barbersData);

    } catch (\Exception $e) {
        Log::error('Get barbers error: ' . $e->getMessage());
        return AuthResult::error('حدث خطأ أثناء جلب الحلاقين: ' . $e->getMessage(), null, 500);
    }
}

    /**
     * جلب بيانات حلاق معين مع تقييمه وإحصائياته
     */
    public function getBarber(User $salonOwner, int $barberId): AuthResult
    {
        try {
            $salon = $salonOwner->ownedSalon;

            if (!$salon) {
                return AuthResult::error('لا يوجد صالون تابع لك', null, 404);
            }

            $barber = $salon->barbers()
                ->select('users.id', 'users.name', 'users.phone', 'users.is_active')
                ->where('users.id', $barberId)
                ->first();

            if (!$barber) {
                return AuthResult::error('الحلاق غير موجود', null, 404);
            }

            $averageRating = $this->getBarberAverageRating($barber->id);
            $weeklyBookings = $this->getBarberWeeklyBookings($barber->id);
            $totalBookings = $this->getBarberTotalBookings($barber->id);
            $completedBookings = $this->getBarberCompletedBookings($barber->id);
            $recentRatings = $this->getBarberRecentRatings($barber->id);

            return AuthResult::success(
                'تم جلب بيانات الحلاق بنجاح',
                [
                    'id' => $barber->id,
                    'name' => $barber->name,
                    'phone' => $barber->phone,
                    'is_active' => $barber->is_active,
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
                    'recent_ratings' => $recentRatings,
                ]
            );

        } catch (\Exception $e) {
            return AuthResult::error(
                'حدث خطأ أثناء جلب بيانات الحلاق',
                config('app.debug') ? $e->getMessage() : null,
                500
            );
        }
    }

    /**
     * تحديث بيانات حلاق
     */
    public function updateBarber(User $salonOwner, int $barberId, array $data): AuthResult
    {
        try {
            return DB::transaction(function () use ($salonOwner, $barberId, $data) {

                $salon = $salonOwner->ownedSalon;

                if (!$salon) {
                    return AuthResult::error('لا يوجد صالون تابع لك', null, 404);
                }

                $barber = $salon->barbers()->where('users.id', $barberId)->first();

                if (!$barber) {
                    return AuthResult::error('الحلاق غير موجود', null, 404);
                }

                if (isset($data['name'])) {
                    $barber->name = $data['name'];
                }

                if (isset($data['phone'])) {
                    $existingUser = User::where('phone', $data['phone'])
                        ->where('id', '!=', $barberId)
                        ->first();

                    if ($existingUser) {
                        return AuthResult::error('رقم الهاتف مستخدم بالفعل', null, 422);
                    }
                    $barber->phone = $data['phone'];
                }

                $barber->save();

                Log::info('Barber updated', [
                    'barber_id' => $barber->id,
                    'updated_by' => $salonOwner->id
                ]);

                return AuthResult::success(
                    'تم تحديث بيانات الحلاق بنجاح',
                    [
                        'id' => $barber->id,
                        'name' => $barber->name,
                        'phone' => $barber->phone,
                        'is_active' => $barber->is_active
                    ]
                );
            });

        } catch (\Exception $e) {
            Log::error('Update barber error: ' . $e->getMessage());
            return AuthResult::error(
                'حدث خطأ أثناء تحديث بيانات الحلاق',
                config('app.debug') ? $e->getMessage() : null,
                500
            );
        }
    }

    /**
     * إيقاف حلاق مؤقتاً (تعطيل الحساب)
     */
    public function deactivateBarber(User $salonOwner, int $barberId): AuthResult
    {
        try {
            $salon = $salonOwner->ownedSalon;

            if (!$salon) {
                return AuthResult::error('لا يوجد صالون تابع لك', null, 404);
            }

            $barber = $salon->barbers()->where('users.id', $barberId)->first();

            if (!$barber) {
                return AuthResult::error('الحلاق غير موجود', null, 404);
            }

            if (!$barber->is_active) {
                return AuthResult::error('الحلاق بالفعل موقوف', null, 400);
            }

            $barber->is_active = false;
            $barber->save();

            Log::info('Barber deactivated', [
                'barber_id' => $barberId,
                'deactivated_by' => $salonOwner->id
            ]);

            return AuthResult::success(
                'تم إيقاف الحلاق بنجاح',
                [
                    'id' => $barber->id,
                    'name' => $barber->name,
                    'phone' => $barber->phone,
                    'is_active' => false
                ]
            );

        } catch (\Exception $e) {
            Log::error('Deactivate barber error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء إيقاف الحلاق', config('app.debug') ? $e->getMessage() : null, 500);
        }
    }

    /**
     * تفعيل حلاق
     */
    public function activateBarber(User $salonOwner, int $barberId): AuthResult
    {
        try {
            $salon = $salonOwner->ownedSalon;

            if (!$salon) {
                return AuthResult::error('لا يوجد صالون تابع لك', null, 404);
            }

            $barber = $salon->barbers()->where('users.id', $barberId)->first();

            if (!$barber) {
                return AuthResult::error('الحلاق غير موجود', null, 404);
            }

            if ($barber->is_active) {
                return AuthResult::error('الحلاق بالفعل مفعل', null, 400);
            }

            $barber->is_active = true;
            $barber->save();

            return AuthResult::success(
                'تم تفعيل الحلاق بنجاح',
                [
                    'id' => $barber->id,
                    'name' => $barber->name,
                    'phone' => $barber->phone,
                    'is_active' => true
                ]
            );

        } catch (\Exception $e) {
            Log::error('Activate barber error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء تفعيل الحلاق', config('app.debug') ? $e->getMessage() : null, 500);
        }
    }

    /**
     * تبديل حالة الحلاق
     */
    public function toggleBarberStatus(User $salonOwner, int $barberId): AuthResult
    {
        try {
            $salon = $salonOwner->ownedSalon;

            if (!$salon) {
                return AuthResult::error('لا يوجد صالون تابع لك', null, 404);
            }

            $barber = $salon->barbers()->where('users.id', $barberId)->first();

            if (!$barber) {
                return AuthResult::error('الحلاق غير موجود', null, 404);
            }

            $oldStatus = $barber->is_active;
            $barber->is_active = !$barber->is_active;
            $barber->save();

            $statusText = $barber->is_active ? 'تفعيل' : 'إيقاف';

            return AuthResult::success(
                "تم {$statusText} الحلاق بنجاح",
                [
                    'id' => $barber->id,
                    'name' => $barber->name,
                    'phone' => $barber->phone,
                    'is_active' => $barber->is_active
                ]
            );

        } catch (\Exception $e) {
            Log::error('Toggle barber status error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء تغيير حالة الحلاق', config('app.debug') ? $e->getMessage() : null, 500);
        }
    }

    /**
     * حذف حلاق (Soft Delete)
     */

public function deleteBarber(User $salonOwner, int $barberId): AuthResult
{
    try {
        // 1. التحقق من وجود الصالون
        $salon = $salonOwner->ownedSalon;
        if (!$salon) {
            return AuthResult::error('لا يوجد صالون تابع لك', null, 404);
        }

        // 2. جلب الحلاق من الصالون
        $barber = $salon->barbers()->where('users.id', $barberId)->first();
        if (!$barber) {
            return AuthResult::error('الحلاق غير موجود', null, 404);
        }

        // 3. التحقق من صلاحيات الحلاق
        $roles = $barber->getRoleNames()->toArray();
        $isSalonOwner = ($barber->id === $salonOwner->id);
        $hasSalonOwnerRole = in_array('salon_owner', $roles);
        $hasOnlyBarberRole = (count($roles) === 1 && in_array('barber', $roles));

        // 4. حالة: الحلاق هو صاحب الصالون نفسه
        if ($isSalonOwner) {
            if ($hasSalonOwnerRole) {
                // إزالة صلاحية barber فقط (لا يتم حذف الحساب)
                $barber->removeRole('barber');
                $barber->salons()->detach($salon->id);

                Log::info('تم إزالة صلاحية الحلاق من صاحب الصالون', [
                    'user_id' => $barber->id,
                    'salon_id' => $salon->id,
                    'remaining_roles' => $barber->getRoleNames()
                ]);

                return AuthResult::success('تم إزالة صلاحية الحلاق من صاحب الصالون بنجاح', [
                    'id' => $barber->id,
                    'name' => $barber->name,
                    'phone' => $barber->phone,
                    'action' => 'role_removed',
                    'remaining_roles' => $barber->getRoleNames(),
                ]);
            }

            if ($hasOnlyBarberRole) {
                // حذف الحساب نهائياً (حالة نادرة)
                $barber->delete();

                Log::info('تم حذف صاحب الصالون (لديه صلاحية barber فقط)', [
                    'user_id' => $barber->id,
                    'salon_id' => $salon->id,
                ]);

                return AuthResult::success('تم حذف الحلاق بنجاح', [
                    'id' => $barber->id,
                    'name' => $barber->name,
                    'phone' => $barber->phone,
                    'action' => 'deleted',
                ]);
            }
        }

        // 5. حالة: حلاق عادي (ليس صاحب الصالون)
        if ($hasOnlyBarberRole) {
            // حذف الحساب نهائياً
            $barber->delete();

            Log::info('تم حذف حلاق عادي', [
                'user_id' => $barber->id,
                'salon_id' => $salon->id,
            ]);

            return AuthResult::success('تم حذف الحلاق بنجاح', [
                'id' => $barber->id,
                'name' => $barber->name,
                'phone' => $barber->phone,
                'action' => 'deleted',
            ]);
        }

        // 6. حالة: الحلاق لديه صلاحيات أخرى (ليس فقط barber)
        // فقط قم بإزالة العلاقة مع الصالون
        $barber->salons()->detach($salon->id);

        Log::info('تم إزالة الحلاق من الصالون (مع الاحتفاظ بالحساب)', [
            'user_id' => $barber->id,
            'salon_id' => $salon->id,
            'roles' => $roles,
        ]);

        return AuthResult::success('تم إزالة الحلاق من الصالون بنجاح', [
            'id' => $barber->id,
            'name' => $barber->name,
            'phone' => $barber->phone,
            'action' => 'removed_from_salon',
            'roles' => $roles,
        ]);

    } catch (\Exception $e) {
        Log::error('Delete barber error: ' . $e->getMessage());
        return AuthResult::error('حدث خطأ أثناء حذف الحلاق', config('app.debug') ? $e->getMessage() : null, 500);
    }
}
    /**
     * إضافة أوقات العمل (يقوم بها الحلاق بعد تسجيل الدخول)
     */
    public function updateWorkingHours(User $barber, array $data): AuthResult
    {
        try {
            return DB::transaction(function () use ($barber, $data) {

                if (!$barber->hasRole('barber')) {
                    return AuthResult::error('هذا المستخدم ليس حلاقاً', null, 403);
                }

                $barber->workingHours()->delete();

                foreach ($data['working_hours'] as $hours) {
                    WorkingHour::create([
                        'workable_type' => User::class,
                        'workable_id' => $barber->id,
                        'day_of_week' => $hours['day'],
                        'is_open' => true,
                        'shift1_start' => $hours['start'],
                        'shift1_end' => $hours['end'],
                    ]);
                }

                $allDays = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
                $workingDays = array_column($data['working_hours'], 'day');

                foreach ($allDays as $day) {
                    if (!in_array($day, $workingDays)) {
                        WorkingHour::create([
                            'workable_type' => User::class,
                            'workable_id' => $barber->id,
                            'day_of_week' => $day,
                            'is_open' => false,
                        ]);
                    }
                }

                Log::info('Working hours updated', [
                    'barber_id' => $barber->id
                ]);

                return AuthResult::success(
                    'تم تحديث أوقات العمل بنجاح',
                    [
                        'id' => $barber->id,
                        'name' => $barber->name,
                        'phone' => $barber->phone,
                        'working_hours' => $barber->fresh('workingHours')->workingHours,
                    ]
                );

            });
        } catch (\Exception $e) {
            Log::error('Update working hours error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء تحديث أوقات العمل', config('app.debug') ? $e->getMessage() : null, 500);
        }
    }

    /**
     * جلب أوقات العمل الحالية
     */
    public function getWorkingHours(User $barber): AuthResult
    {
        try {
            if (!$barber->hasRole('barber')) {
                return AuthResult::error('هذا المستخدم ليس حلاقاً', null, 403);
            }

            $workingHours = $barber->workingHours()
                ->orderByRaw("FIELD(day_of_week, 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday')")
                ->get();

            return AuthResult::success(
                'تم جلب أوقات العمل بنجاح',
                [
                    'id' => $barber->id,
                    'name' => $barber->name,
                    'phone' => $barber->phone,
                    'working_hours' => $workingHours,
                ]
            );

        } catch (\Exception $e) {
            Log::error('Get working hours error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء جلب أوقات العمل', config('app.debug') ? $e->getMessage() : null, 500);
        }
    }

    /**
     * إعادة إرسال بيانات الدخول للحلاق
     */
    public function resendBarberCredentials(User $salonOwner, int $barberId): AuthResult
    {
        try {
            $salon = $salonOwner->ownedSalon;

            if (!$salon) {
                return AuthResult::error('لا يوجد صالون تابع لك', null, 404);
            }

            $barber = $salon->barbers()->where('users.id', $barberId)->first();

            if (!$barber) {
                return AuthResult::error('الحلاق غير موجود', null, 404);
            }

            $result = $this->ultraMsg->resendCredentials($barber, $salon, 'barber');

            if ($result['success']) {
                return AuthResult::success('تم إعادة إرسال بيانات الدخول بنجاح', null, 200);
            }

            return AuthResult::error('فشل إعادة إرسال البيانات: ' . ($result['error'] ?? 'خطأ غير معروف'), null, 500);

        } catch (\Exception $e) {
            Log::error('Resend barber credentials error: ' . $e->getMessage());
            return AuthResult::error('حدث خطأ أثناء إعادة الإرسال', config('app.debug') ? $e->getMessage() : null, 500);
        }
    }

    // ===================== دوال مساعدة =====================

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

    /**
     * جلب آخر 5 تقييمات للحلاق
     */
    private function getBarberRecentRatings(int $barberId): array
    {
        $ratings = Rating::where('barber_id', $barberId)
            ->where('is_approved', true)
            ->with('customer')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return $ratings->map(function ($rating) {
            return [
                'id' => $rating->id,
                'customer_name' => $rating->customer->name,
                'rating' => $rating->rating,
                'comment' => $rating->comment,
                'created_at' => $rating->created_at->diffForHumans(),
            ];
        })->toArray();
    }
}
