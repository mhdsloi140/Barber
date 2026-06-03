<?php
// app/Services/Barber/BarberServiceService.php

namespace App\Services\Barber;

use App\Models\User;
use App\Models\BarberService;
use App\Services\AuthResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\Notification\FirebaseNotificationService;
class BarberServiceService
{
    /**
     * إضافة خدمة جديدة للحلاق
     */
    public function addService(User $barber, array $data): AuthResult
    {
        try {
            return DB::transaction(function () use ($barber, $data) {

                if (!$barber->hasRole('barber')) {
                    return AuthResult::error('هذا المستخدم ليس حلاقاً', null, 403);
                }

                // إنشاء الخدمة
                $service = BarberService::create([
                    'barber_id' => $barber->id,
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                    'price' => $data['price'],
                    'duration_minutes' => $data['duration_minutes'],
                    'is_active' => $data['is_active'] ?? true,
                ]);

                //  إرسال إشعارات لجميع الزبائن عن الخدمة الجديدة
                try {
                    $notificationService = app(FirebaseNotificationService::class);

                    // جلب الصالون الذي يعمل فيه الحلاق
                    $salon = $barber->salons()->first();

                    if ($salon) {
                        // إرسال إشعار لجميع الزبائن
                        $notificationService->notifySalonCustomersAboutNewService($service, $barber, $salon);

                        // إرسال إشعار لمدير الصالون
                        $notificationService->notifySalonOwnerAboutNewService($service, $barber, $salon);
                    } else {
                        // إذا لم يكن للحلاق صالون، أرسل للجميع
                        $notificationService->notifyAllCustomersAboutNewService($service, $barber);
                    }

                } catch (\Exception $e) {
                    Log::error('Failed to send new service notifications: ' . $e->getMessage());
                }


                return AuthResult::success(
                    'تم إضافة الخدمة بنجاح',
                    $service,
                    201
                );
            });
        } catch (\Exception $e) {
            Log::error('Add barber service error: ' . $e->getMessage());

            return AuthResult::error(
                'حدث خطأ أثناء إضافة الخدمة',
                config('app.debug') ? $e->getMessage() : null,
                500
            );
        }
    }

    /**
     * جلب جميع خدمات الحلاق
     */
    public function getServices(User $barber, int $perPage = 10): AuthResult
    {
        try {
            if (!$barber->hasRole('barber')) {
                return AuthResult::error('هذا المستخدم ليس حلاقاً', null, 403);
            }

            $services = BarberService::where('barber_id', $barber->id)
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);


            $items = collect($services->items())->map(function ($service) {
                return [
                    'id' => $service->id,
                    'name' => $service->name,
                    'description' => $service->description,
                    'price' => (float) $service->price,
                    'duration_minutes' => (int) $service->duration_minutes,
                    'is_active' => (bool) $service->is_active,
                    'status_text' => $service->is_active ? 'نشط' : 'موقوف',
                    'created_at' => $service->created_at,
                    'updated_at' => $service->updated_at,
                ];
            });

          
            $statistics = [
                'total' => BarberService::where('barber_id', $barber->id)->count(),
                'active' => BarberService::where('barber_id', $barber->id)->where('is_active', true)->count(),
                'inactive' => BarberService::where('barber_id', $barber->id)->where('is_active', false)->count(),
            ];

            return AuthResult::success('تم جلب الخدمات بنجاح', [
                'statistics' => $statistics,
                'services' => $items,
                'pagination' => [
                    'current_page' => $services->currentPage(),
                    'last_page' => $services->lastPage(),
                    'per_page' => $services->perPage(),
                    'total' => $services->total(),
                    'next_page_url' => $services->nextPageUrl(),
                    'prev_page_url' => $services->previousPageUrl(),
                    'has_more_pages' => $services->hasMorePages(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get barber services error: ' . $e->getMessage());

            return AuthResult::error(
                'حدث خطأ أثناء جلب الخدمات',
                config('app.debug') ? $e->getMessage() : null,
                500
            );
        }
    }

    /**
     * جلب خدمة محددة
     */
    public function getService(User $barber, int $serviceId): AuthResult
    {
        try {
            if (!$barber->hasRole('barber')) {
                return AuthResult::error('هذا المستخدم ليس حلاقاً', null, 403);
            }

            $service = BarberService::where('barber_id', $barber->id)
                ->where('id', $serviceId)
                ->first();

            if (!$service) {
                return AuthResult::error('الخدمة غير موجودة', null, 404);
            }

            return AuthResult::success(
                'تم جلب الخدمة بنجاح',
                $service
            );

        } catch (\Exception $e) {
            Log::error('Get barber service error: ' . $e->getMessage());

            return AuthResult::error(
                'حدث خطأ أثناء جلب الخدمة',
                config('app.debug') ? $e->getMessage() : null,
                500
            );
        }
    }

    /**
     * تحديث خدمة
     */
    public function updateService(User $barber, int $serviceId, array $data): AuthResult
    {
        try {
            return DB::transaction(function () use ($barber, $serviceId, $data) {

                if (!$barber->hasRole('barber')) {
                    return AuthResult::error('هذا المستخدم ليس حلاقاً', null, 403);
                }

                $service = BarberService::where('barber_id', $barber->id)
                    ->where('id', $serviceId)
                    ->first();

                if (!$service) {
                    return AuthResult::error('الخدمة غير موجودة', null, 404);
                }

                // dd($service);

                $service->update([
                    'name' => $data['name'] ?? $service->name,
                    // 'name_ar' => $data['name_ar'] ?? $service->name_ar,
                    'description' => $data['description'] ?? $service->description,
                    // 'description_ar' => $data['description_ar'] ?? $service->description_ar,
                    'price' => $data['price'] ?? $service->price,
                    'duration_minutes' => $data['duration_minutes'] ?? $service->duration_minutes,
                    'is_active' => $data['is_active'] ?? $service->is_active,
                ]);



                return AuthResult::success(
                    'تم تحديث الخدمة بنجاح',
                    $service->fresh()
                );

            });
        } catch (\Exception $e) {
            Log::error('Update barber service error: ' . $e->getMessage());

            return AuthResult::error(
                'حدث خطأ أثناء تحديث الخدمة',
                config('app.debug') ? $e->getMessage() : null,
                500
            );
        }
    }

    /**
     * حذف خدمة (Soft Delete)
     */
    public function deleteService(User $barber, int $serviceId): AuthResult
    {
        try {
            if (!$barber->hasRole('barber')) {
                return AuthResult::error('هذا المستخدم ليس حلاقاً', null, 403);
            }

            $service = BarberService::where('barber_id', $barber->id)
                ->where('id', $serviceId)
                ->first();

            if (!$service) {
                return AuthResult::error('الخدمة غير موجودة', null, 404);
            }

            // التحقق من وجود مواعيد مرتبطة بالخدمة
            // if ($service->appointments()->count() > 0) {
            //     return AuthResult::error('لا يمكن حذف الخدمة لأن هناك مواعيد مرتبطة بها', null, 400);
            // }

            $service->delete();

            Log::info('Barber service deleted', [
                'service_id' => $serviceId,
                'barber_id' => $barber->id
            ]);

            return AuthResult::success('تم حذف الخدمة بنجاح');

        } catch (\Exception $e) {
            Log::error('Delete barber service error: ' . $e->getMessage());

            return AuthResult::error(
                'حدث خطأ أثناء حذف الخدمة',
                config('app.debug') ? $e->getMessage() : null,
                500
            );
        }
    }

    /**
     * حذف نهائي لخدمة (Force Delete)
     */
    public function forceDeleteService(User $barber, int $serviceId): AuthResult
    {
        try {
            if (!$barber->hasRole('barber')) {
                return AuthResult::error('هذا المستخدم ليس حلاقاً', null, 403);
            }

            $service = BarberService::withTrashed()
                ->where('barber_id', $barber->id)
                ->where('id', $serviceId)
                ->first();

            if (!$service) {
                return AuthResult::error('الخدمة غير موجودة', null, 404);
            }

            $service->forceDelete();

            Log::info('Barber service force deleted', [
                'service_id' => $serviceId,
                'barber_id' => $barber->id
            ]);

            return AuthResult::success('تم حذف الخدمة نهائياً');

        } catch (\Exception $e) {
            Log::error('Force delete barber service error: ' . $e->getMessage());

            return AuthResult::error(
                'حدث خطأ أثناء حذف الخدمة',
                config('app.debug') ? $e->getMessage() : null,
                500
            );
        }
    }

    /**
     * تبديل حالة الخدمة (تفعيل/تعطيل)
     */
    public function toggleServiceStatus(User $barber, int $serviceId): AuthResult
    {
        try {
            if (!$barber->hasRole('barber')) {
                return AuthResult::error('هذا المستخدم ليس حلاقاً', null, 403);
            }

            $service = BarberService::where('barber_id', $barber->id)
                ->where('id', $serviceId)
                ->first();

            if (!$service) {
                return AuthResult::error('الخدمة غير موجودة', null, 404);
            }

            $service->is_active = !$service->is_active;
            $service->save();

            $statusText = $service->is_active ? 'تفعيل' : 'تعطيل';

            Log::info('Barber service status toggled', [
                'service_id' => $serviceId,
                'barber_id' => $barber->id,
                'new_status' => $service->is_active
            ]);

            return AuthResult::success(
                "تم {$statusText} الخدمة بنجاح",
                $service
            );

        } catch (\Exception $e) {
            Log::error('Toggle barber service status error: ' . $e->getMessage());

            return AuthResult::error(
                'حدث خطأ أثناء تغيير حالة الخدمة',
                config('app.debug') ? $e->getMessage() : null,
                500
            );
        }
    }

    /**
     * جلب الخدمات المحذوفة (سلة المهملات)
     */
    public function getTrashedServices(User $barber): AuthResult
    {
        try {
            if (!$barber->hasRole('barber')) {
                return AuthResult::error('هذا المستخدم ليس حلاقاً', null, 403);
            }

            $services = BarberService::onlyTrashed()
                ->where('barber_id', $barber->id)
                ->orderBy('deleted_at', 'desc')
                ->get();

            return AuthResult::success(
                'تم جلب الخدمات المحذوفة بنجاح',
                $services
            );

        } catch (\Exception $e) {
            Log::error('Get trashed barber services error: ' . $e->getMessage());

            return AuthResult::error(
                'حدث خطأ أثناء جلب الخدمات المحذوفة',
                config('app.debug') ? $e->getMessage() : null,
                500
            );
        }
    }

    /**
     * استعادة خدمة محذوفة
     */
    public function restoreService(User $barber, int $serviceId): AuthResult
    {
        try {
            if (!$barber->hasRole('barber')) {
                return AuthResult::error('هذا المستخدم ليس حلاقاً', null, 403);
            }

            $service = BarberService::onlyTrashed()
                ->where('barber_id', $barber->id)
                ->where('id', $serviceId)
                ->first();

            if (!$service) {
                return AuthResult::error('الخدمة غير موجودة أو غير محذوفة', null, 404);
            }

            $service->restore();

            Log::info('Barber service restored', [
                'service_id' => $serviceId,
                'barber_id' => $barber->id
            ]);

            return AuthResult::success(
                'تم استعادة الخدمة بنجاح',
                $service
            );

        } catch (\Exception $e) {
            Log::error('Restore barber service error: ' . $e->getMessage());

            return AuthResult::error(
                'حدث خطأ أثناء استعادة الخدمة',
                config('app.debug') ? $e->getMessage() : null,
                500
            );
        }
    }
}
