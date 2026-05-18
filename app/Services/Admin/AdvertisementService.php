<?php
// app/Services/AdvertisementService.php

namespace App\Services\Admin;

use App\Models\Advertisement;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdvertisementService
{
    /**
     * عرض جميع الإعلانات
     */
    public function getAllAdvertisements($perPage = 10)
    {
        return Advertisement::orderBy('sort_order')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * إنشاء إعلان جديد
     */
public function createAdvertisement(array $data, array $images = [])
{
    try {
        DB::beginTransaction();

        // التحقق من وجود إعلان دائم (بدون تاريخ انتهاء)
        $permanentAd = Advertisement::where('is_active', true)
            ->whereNull('ends_at')
            ->first();

        if ($permanentAd) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => ' لا يمكن إضافة إعلان جديد. يوجد إعلان دائم نشط حالياً. قم بحذف الإعلان الدائم أولاً.'
            ];
        }

        // التحقق من وجود أي إعلان نشط (لم ينتهي تاريخه بعد)
        $activeAd = Advertisement::where('is_active', true)
            ->whereNotNull('ends_at')
            ->where('ends_at', '>=', now())
            ->first();

        if ($activeAd) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => ' لا يمكن إضافة إعلان جديد. يوجد إعلان نشط حتى تاريخ ' . $activeAd->ends_at->format('Y-m-d')
            ];
        }

        // إذا كان المستخدم يريد إضافة إعلان دائم (بدون تاريخ انتهاء)
        if (empty($data['ends_at'])) {
            // التحقق من عدم وجود أي إعلان دائم آخر
            $anyPermanentAd = Advertisement::whereNull('ends_at')->exists();
            if ($anyPermanentAd) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => ' لا يمكن إضافة إعلان دائم. يوجد إعلان دائم بالفعل.'
                ];
            }

            // التحقق من عدم وجود إعلانات مستقبلية بعد هذا الإعلان الدائم
            $futureAds = Advertisement::where('starts_at', '>', now())->exists();
            if ($futureAds) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => ' لا يمكن إضافة إعلان دائم. يوجد إعلانات مستقبلية مجدولة.'
                ];
            }
        }

        // التحقق من صحة التواريخ
        if (isset($data['starts_at']) && isset($data['ends_at'])) {
            $startDate = Carbon::parse($data['starts_at']);
            $endDate = Carbon::parse($data['ends_at']);

            if ($startDate->greaterThan($endDate)) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => ' تاريخ البدء لا يمكن أن يكون بعد تاريخ الانتهاء'
                ];
            }
        }

        // إنشاء الإعلان
        $advertisement = Advertisement::create([
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'link_url' => $data['link_url'] ?? null,
            'is_active' => $data['is_active'] ?? false,
            'sort_order' => $this->getNextSortOrder(),
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null
        ]);

        // رفع الصور
        if (!empty($images)) {
            $this->uploadImages($advertisement, $images);
        }

        DB::commit();

        $message = ' تم إنشاء الإعلان بنجاح';
        if (empty($data['ends_at'])) {
            $message = ' تم إنشاء الإعلان الدائم بنجاح. لن يمكن إضافة أي إعلان آخر بعده.';
        }

        return [
            'success' => true,
            'message' => $message,
            'data' => $advertisement
        ];

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Error creating advertisement: ' . $e->getMessage());

        return [
            'success' => false,
            'message' => ' حدث خطأ أثناء إنشاء الإعلان: ' . $e->getMessage()
        ];
    }
}
    /**
     * تحديث إعلان موجود
     */
    public function updateAdvertisement(Advertisement $advertisement, array $data, array $newImages = [], array $deleteImageIds = [])
    {
        try {
            DB::beginTransaction();

            // تحديث بيانات الإعلان
            $advertisement->update([
                'title' => $data['title'] ?? $advertisement->title,
                'description' => $data['description'] ?? $advertisement->description,
                'link_url' => $data['link_url'] ?? $advertisement->link_url,
                'is_active' => $data['is_active'] ?? $advertisement->is_active,
                'starts_at' => $data['starts_at'] ?? $advertisement->starts_at,
                'ends_at' => $data['ends_at'] ?? $advertisement->ends_at
            ]);

            // حذف الصور المحددة
            if (!empty($deleteImageIds)) {
                $this->deleteImages($advertisement, $deleteImageIds);
            }

            // رفع صور جديدة
            if (!empty($newImages)) {
                $this->uploadImages($advertisement, $newImages);
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'تم تحديث الإعلان بنجاح',
                'data' => $advertisement->fresh()
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating advertisement: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء تحديث الإعلان: ' . $e->getMessage()
            ];
        }
    }

    /**
     * حذف إعلان
     */
    public function deleteAdvertisement(Advertisement $advertisement)
    {
        try {
            DB::beginTransaction();

            // حذف جميع الصور
            $advertisement->clearMediaCollection('ad_images');

            // حذف الإعلان
            $advertisement->delete();

            DB::commit();

            return [
                'success' => true,
                'message' => 'تم حذف الإعلان بنجاح'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting advertisement: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء حذف الإعلان: ' . $e->getMessage()
            ];
        }
    }

    /**
     * رفع الصور للإعلان
     */
    private function uploadImages(Advertisement $advertisement, array $images)
    {
        foreach ($images as $image) {
            if ($image instanceof UploadedFile) {
                $advertisement->addMedia($image)
                    ->toMediaCollection('ad_images');
            }
        }
    }

    /**
     * حذف صور محددة
     */
    private function deleteImages(Advertisement $advertisement, array $imageIds)
    {
        $medias = $advertisement->getMedia('ad_images')
            ->whereIn('id', $imageIds);

        foreach ($medias as $media) {
            $media->delete();
        }
    }

    /**
     * الحصول على ترتيب التالي
     */
    private function getNextSortOrder()
    {
        $maxOrder = Advertisement::max('sort_order');
        return $maxOrder ? $maxOrder + 1 : 1;
    }

    /**
     * تحديث ترتيب الإعلانات
     */
    public function updateOrder(array $orders)
    {
        try {
            DB::beginTransaction();

            foreach ($orders as $order) {
                Advertisement::where('id', $order['id'])
                    ->update(['sort_order' => $order['position']]);
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'تم تحديث الترتيب بنجاح'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating order: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء تحديث الترتيب'
            ];
        }
    }

    /**
     * تفعيل/تعطيل إعلان
     */
    public function toggleStatus(Advertisement $advertisement)
    {
        try {
            $advertisement->update([
                'is_active' => !$advertisement->is_active
            ]);

            return [
                'success' => true,
                'message' => $advertisement->is_active ? 'تم تفعيل الإعلان' : 'تم تعطيل الإعلان',
                'is_active' => $advertisement->is_active
            ];

        } catch (\Exception $e) {
            Log::error('Error toggling status: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء تغيير الحالة'
            ];
        }
    }

    /**
     * الحصول على إعلان مع صوره
     */
    public function getAdvertisementWithImages(Advertisement $advertisement)
    {
        return [
            'advertisement' => $advertisement,
            'images' => $advertisement->images,
            'first_image' => $advertisement->first_image
        ];
    }

    /**
     * نسخ إعلان
     */
    public function duplicateAdvertisement(Advertisement $advertisement)
    {
        try {
            DB::beginTransaction();

            $newAdvertisement = $advertisement->replicate();
            $newAdvertisement->title = $advertisement->title . ' (نسخة)';
            $newAdvertisement->sort_order = $this->getNextSortOrder();
            $newAdvertisement->save();

            // نسخ الصور
            foreach ($advertisement->getMedia('ad_images') as $media) {
                $newAdvertisement->addMedia($media->getPath())
                    ->preservingOriginal()
                    ->toMediaCollection('ad_images');
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'تم نسخ الإعلان بنجاح',
                'data' => $newAdvertisement
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error duplicating advertisement: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء نسخ الإعلان'
            ];
        }
    }
}
