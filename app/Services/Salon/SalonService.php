<?php
// app/Services/Salon/SalonService.php

namespace App\Services\Salon;

use App\Models\Salon;
use App\Models\User;
use App\Services\AuthResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SalonService
{
    /**
     * الحصول على بيانات الصالون الخاص بصاحب الصالون
     */
    public function getSalon(User $owner): AuthResult
    {
        try {
            $salon = $owner->ownedSalon;

            if (!$salon) {
                return AuthResult::error('لا يوجد صالون تابع لك', null, 404);
            }

            // تحميل العلاقات
            $salon->load('barbers');

            // تجهيز البيانات
            $data = [
                'id' => $salon->id,
                'name' => $salon->name,
                'address' => $salon->address,
                'latitude' => $salon->latitude,
                'longitude' => $salon->longitude,
                'phone' => $salon->phone,
                'is_active' => $salon->is_active,
                'images' => $salon->images_urls,
                'main_image' => $salon->main_image_url,
                'barbers_count' => $salon->barbers_count,
                'created_at' => $salon->created_at,
                'updated_at' => $salon->updated_at,
            ];

            return AuthResult::success('تم جلب بيانات الصالون بنجاح', $data);

        } catch (\Exception $e) {
            Log::error('Get salon error: ' . $e->getMessage());

            return AuthResult::error(
                'حدث خطأ أثناء جلب بيانات الصالون',
                config('app.debug') ? $e->getMessage() : null,
                500
            );
        }
    }

    /**
     * تحديث بيانات الصالون
     */
    public function updateSalon(User $owner, array $data): AuthResult
    {
        try {
            return DB::transaction(function () use ($owner, $data) {

                $salon = $owner->ownedSalon;

                if (!$salon) {
                    return AuthResult::error('لا يوجد صالون تابع لك', null, 404);
                }

                // تحديث البيانات
                $salon->update($data);
                // تجهيز البيانات بعد التحديث
                $result = [
                    'id' => $salon->id,
                    'name' => $salon->name,
                    'address' => $salon->address,
                    'latitude' => $salon->latitude,
                    'longitude' => $salon->longitude,
                    'phone' => $salon->phone,
                    'is_active' => $salon->is_active,
                    'updated_at' => $salon->updated_at,
                ];

                return AuthResult::success('تم تحديث بيانات الصالون بنجاح', $result);

            });
        } catch (\Exception $e) {
            Log::error('Update salon error: ' . $e->getMessage());

            return AuthResult::error(
                'حدث خطأ أثناء تحديث بيانات الصالون',
                config('app.debug') ? $e->getMessage() : null,
                500
            );
        }
    }

    /**
     * إنشاء صالون جديد (عند تسجيل صاحب صالون)
     */
    public function createSalon(User $owner, array $data): AuthResult
    {
        try {
            return DB::transaction(function () use ($owner, $data) {

                // التحقق من عدم وجود صالون مسبق
                if ($owner->ownedSalon) {
                    return AuthResult::error('لديك صالون بالفعل', null, 400);
                }

                $salon = Salon::create([
                    'name' => $data['name'],
                    'owner_id' => $owner->id,
                    'address' => $data['address'],
                    'phone' => $data['phone'] ?? null,
                    'latitude' => $data['latitude'] ?? null,
                    'longitude' => $data['longitude'] ?? null,
                    'is_active' => true,
                ]);

                Log::info('Salon created', [
                    'salon_id' => $salon->id,
                    'owner_id' => $owner->id
                ]);

                return AuthResult::success('تم إنشاء الصالون بنجاح', $salon, 201);

            });
        } catch (\Exception $e) {
            Log::error('Create salon error: ' . $e->getMessage());

            return AuthResult::error(
                'حدث خطأ أثناء إنشاء الصالون',
                config('app.debug') ? $e->getMessage() : null,
                500
            );
        }
    }

    /**
     * الحصول على إحصائيات الصالون
     */
    public function getSalonStats(User $owner): AuthResult
    {
        try {
            $salon = $owner->ownedSalon;

            if (!$salon) {
                return AuthResult::error('لا يوجد صالون تابع لك', null, 404);
            }

            $barbersCount = $salon->barbers()->count();
            $activeBarbersCount = $salon->barbers()->wherePivot('is_active', true)->count();

            // جلب خدمات الحلاقين
            $barberIds = $salon->barbers()->pluck('users.id')->toArray();
            $servicesCount = \App\Models\BarberService::whereIn('barber_id', $barberIds)->count();
            $activeServicesCount = \App\Models\BarberService::whereIn('barber_id', $barberIds)
                ->where('is_active', true)
                ->count();

            $stats = [
                'barbers' => [
                    'total' => $barbersCount,
                    'active' => $activeBarbersCount,
                    'inactive' => $barbersCount - $activeBarbersCount,
                ],
                'services' => [
                    'total' => $servicesCount,
                    'active' => $activeServicesCount,
                    'inactive' => $servicesCount - $activeServicesCount,
                ],
                'images_count' => $salon->getMedia('salon_images')->count(),
            ];

            return AuthResult::success('تم جلب إحصائيات الصالون بنجاح', $stats);

        } catch (\Exception $e) {
            Log::error('Get salon stats error: ' . $e->getMessage());

            return AuthResult::error(
                'حدث خطأ أثناء جلب إحصائيات الصالون',
                config('app.debug') ? $e->getMessage() : null,
                500
            );
        }
    }

    /**
     * تفعيل/تعطيل الصالون
     */
    public function toggleStatus(User $owner): AuthResult
    {
        try {
            $salon = $owner->ownedSalon;

            if (!$salon) {
                return AuthResult::error('لا يوجد صالون تابع لك', null, 404);
            }

            $oldStatus = $salon->is_active;
            $salon->is_active = !$salon->is_active;
            $salon->save();

            $statusText = $salon->is_active ? 'تفعيل' : 'تعطيل';

            Log::info('Salon status toggled', [
                'salon_id' => $salon->id,
                'old_status' => $oldStatus,
                'new_status' => $salon->is_active
            ]);

            return AuthResult::success(
                "تم {$statusText} الصالون بنجاح",
                [
                    'id' => $salon->id,
                    'is_active' => $salon->is_active
                ]
            );

        } catch (\Exception $e) {
            Log::error('Toggle salon status error: ' . $e->getMessage());

            return AuthResult::error(
                'حدث خطأ أثناء تغيير حالة الصالون',
                config('app.debug') ? $e->getMessage() : null,
                500
            );
        }
    }
}
