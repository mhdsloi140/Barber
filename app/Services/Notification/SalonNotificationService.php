<?php


namespace App\Services\Notification;

use App\Models\Salon;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class SalonNotificationService
{
    protected $firebaseService;

    public function __construct(FirebaseNotificationService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * إرسال إشعار للمديرين عند إنشاء صالون جديد
     */
    public function notifyAdminsNewSalon(Salon $salon): void
    {
        $title = ' صالون جديد ينتظر الموافقة';
        $body = $salon->name . ' - المالك: ' . ($salon->owner->name ?? 'غير معروف');

        $data = [
            'type' => 'new_salon',
            'salon_id' => $salon->id,
            'salon_name' => $salon->name,
            'owner_name' => $salon->owner->name ?? '',
            'owner_phone' => $salon->owner->phone ?? '',
            'action_url' => '/admin/salons/' . $salon->id,
        ];

        $result = $this->firebaseService->sendToAdmins($title, $body, $data);

        Log::info('New salon notification sent to admins', [
            'salon_id' => $salon->id,
            'admins_notified' => $result['sent'],
            'total_admins' => $result['total'],
        ]);
    }

    /**
     * إرسال إشعار للمدير عند الموافقة على صالون
     */
    public function notifyAdminsSalonApproved(Salon $salon): void
    {
        $title = ' تمت الموافقة على الصالون';
        $body = 'تمت الموافقة على صالون ' . $salon->name;

        $data = [
            'type' => 'salon_approved',
            'salon_id' => $salon->id,
            'salon_name' => $salon->name,
        ];

        $this->firebaseService->sendToAdmins($title, $body, $data);
    }
}
