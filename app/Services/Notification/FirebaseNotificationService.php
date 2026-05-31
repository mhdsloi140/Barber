<?php

namespace App\Services\Notification;

use App\Models\Appointment;
use App\Models\Salon;
use App\Models\User;
use App\Models\BarberService;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FirebaseNotificationService
{
    protected $messaging;

    public function __construct()
    {
        try {
            $this->messaging = app('firebase.messaging');
        } catch (\Exception $e) {
            Log::warning('Firebase messaging not initialized: ' . $e->getMessage());
            $this->messaging = null;
        }
    }


   /**
 * إرسال إشعار Push وتخزينه في قاعدة البيانات
 */
public function sendPushNotification(User $user, string $title, string $body, array $data = [], ?string $imageUrl = null): bool
{
    //  التحقق من تفعيل الإشعارات للمستخدم
    if (!$user->notifications_enabled) {
        Log::info('User has notifications disabled', ['user_id' => $user->id]);
        return false;
    }

    //  1. تخزين الإشعار في قاعدة البيانات (حتى لو كان معطل، نخزن للإشعارات داخل التطبيق)
    $this->storeNotification($user, $title, $body, $data, $imageUrl);

    //  2. إرسال Push Notification فقط إذا كان الإشعارات مفعلة ولديه توكن
    if (!$this->messaging) {
        Log::warning('Firebase messaging not available');
        return false;
    }

    $token = $user->fcm_token;

    if (empty($token)) {
        Log::info('No FCM token found for user', ['user_id' => $user->id]);
        return false;
    }

    try {
        $notification = Notification::create($title, $body);

        $message = CloudMessage::withTarget('token', $token)
            ->withNotification($notification)
            ->withData($data)
            ->withAndroidConfig([
                'priority' => 'high',
                'notification' => [
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                ],
            ])
            ->withApnsConfig([
                'headers' => [
                    'apns-priority' => '10',
                ],
                'payload' => [
                    'aps' => [
                        'sound' => 'default',
                        'badge' => 1,
                    ],
                ],
            ]);

        $this->messaging->send($message);

        Log::info('Push notification sent', [
            'user_id' => $user->id,
            'notifications_enabled' => $user->notifications_enabled,
        ]);

        return true;

    } catch (\Exception $e) {
        Log::error('Failed to send push notification', [
            'user_id' => $user->id,
            'error' => $e->getMessage(),
        ]);

        if (str_contains($e->getMessage(), 'NOT_FOUND') ||
            str_contains($e->getMessage(), 'UNREGISTERED') ||
            str_contains($e->getMessage(), 'Invalid argument')) {

            $user->update(['fcm_token' => null]);
            Log::info('Invalid FCM token removed', ['user_id' => $user->id]);
        }

        return false;
    }
}
private function storeNotification(User $user, string $title, string $body, array $data = [], ?string $imageUrl = null): void
{
    try {
       Notification::create([
            'user_id' => $user->id,
            'title' => $title,
            'body' => $body,
            'type' => $data['type'] ?? null,
            'data' => $data,
            'image_url' => $imageUrl,
            'is_read' => false,
        ]);

        Log::debug('Notification stored in database', [
            'user_id' => $user->id,
            'type' => $data['type'] ?? null,
        ]);
    } catch (\Exception $e) {
        Log::error('Failed to store notification', [
            'user_id' => $user->id,
            'error' => $e->getMessage(),
        ]);
    }
}
    /**
     *  إرسال إشعار للحلاق عند إنشاء حجز جديد
     */
    public function notifyNewAppointmentToBarber(Salon $salon, Appointment $appointment): void
    {
        $barber = $appointment->barber;
        $customer = $appointment->customer;

        if (!$barber) {
            Log::error('Barber not found', ['appointment_id' => $appointment->id]);
            return;
        }

        $appointmentTime = $this->formatTime($appointment->appointment_time);
        $services = $this->getServicesNames($appointment);

        $title = ' حجز جديد';
        $body = "لديك حجز جديد من {$customer->name} في {$appointmentTime}";

        $data = [
            'type' => 'new_appointment',
            'appointment_id' => (string) $appointment->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'appointment_time' => $appointmentTime,
            'appointment_date' => $this->formatDate($appointment->appointment_date),
            'services' => $services,
            'total_price' => (string) $appointment->total_price,
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            'screen' => 'appointment_details',
        ];

        $this->sendPushNotification($barber, $title, $body, $data);
    }

    /**
     *  إرسال إشعار لمدير الصالون عند إنشاء حجز جديد
     */
    public function notifySalonOwnerAboutNewAppointment(Salon $salon, Appointment $appointment): void
    {
        $salonOwner = $salon->owner;

        if (!$salonOwner) {
            Log::warning('Salon owner not found', ['salon_id' => $salon->id]);
            return;
        }

        $customer = $appointment->customer;
        $barber = $appointment->barber;
        $appointmentTime = $this->formatTime($appointment->appointment_time);
        $services = $this->getServicesNames($appointment);

        $title = ' حجز جديد في صالونك';
        $body = "حجز جديد من {$customer->name} مع {$barber->name} في {$appointmentTime}";

        $data = [
            'type' => 'new_appointment_owner',
            'appointment_id' => (string) $appointment->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'barber_name' => $barber->name,
            'salon_name' => $salon->name,
            'appointment_time' => $appointmentTime,
            'appointment_date' => $this->formatDate($appointment->appointment_date),
            'services' => $services,
            'total_price' => (string) $appointment->total_price,
            'status' => $appointment->status,
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            'screen' => 'appointment_details',
        ];

        $this->sendPushNotification($salonOwner, $title, $body, $data);
    }

    /**
     *  إرسال إشعار للزبون عند قبول الحجز
     */
    public function notifyAppointmentApprovedToCustomer(Appointment $appointment): void
    {
        $customer = $appointment->customer;

        if (!$customer) {
            return;
        }

        $barber = $appointment->barber;
        $appointmentTime = $this->formatTime($appointment->appointment_time);
        $services = $this->getServicesNames($appointment);

        $title = ' تم قبول حجزك';
        $body = "تم قبول حجزك مع {$barber->name} في {$appointmentTime}";

        $data = [
            'type' => 'appointment_approved',
            'appointment_id' => (string) $appointment->id,
            'status' => 'confirmed',
            'barber_name' => $barber->name,
            'appointment_time' => $appointmentTime,
            'appointment_date' => $this->formatDate($appointment->appointment_date),
            'services' => $services,
            'total_price' => (string) $appointment->total_price,
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            'screen' => 'appointment_details',
        ];

        $this->sendPushNotification($customer, $title, $body, $data);
    }

    /**
     *  إرسال إشعار للزبون عند رفض الحجز
     */
    public function notifyAppointmentRejectedToCustomer(Appointment $appointment, ?string $reason = null): void
    {
        $customer = $appointment->customer;

        if (!$customer) {
            return;
        }

        $barber = $appointment->barber;
        $appointmentTime = $this->formatTime($appointment->appointment_time);

        $title = ' تم رفض حجزك';
        $body = "تم رفض حجزك مع {$barber->name}";

        if ($reason) {
            $body .= " بسبب: {$reason}";
        }

        $data = [
            'type' => 'appointment_rejected',
            'appointment_id' => (string) $appointment->id,
            'status' => 'cancelled',
            'reason' => $reason ?? '',
            'barber_name' => $barber->name,
            'appointment_time' => $appointmentTime,
            'appointment_date' => $this->formatDate($appointment->appointment_date),
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            'screen' => 'appointment_details',
        ];

        $this->sendPushNotification($customer, $title, $body, $data);
    }

    /**
     *  إرسال إشعار للزبون عند إكمال الحجز
     */
    public function notifyAppointmentCompletedToCustomer(Appointment $appointment): void
    {
        $customer = $appointment->customer;

        if (!$customer) {
            return;
        }

        $barber = $appointment->barber;

        $title = ' اكتمل حجزك';
        $body = "شكراً لك، نأمل أن تكون راضياً عن الخدمة مع {$barber->name}";

        $data = [
            'type' => 'appointment_completed',
            'appointment_id' => (string) $appointment->id,
            'status' => 'completed',
            'barber_name' => $barber->name,
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            'screen' => 'rate_barber',
        ];

        $this->sendPushNotification($customer, $title, $body, $data);
    }

    /**
     *  إرسال إشعار تذكير للزبون قبل الموعد
     */
    public function sendAppointmentReminder(User $user, Appointment $appointment): void
    {
        $barber = $appointment->barber;
        $appointmentTime = $this->formatTime($appointment->appointment_time);

        $title = ' تذكير بموعدك';
        $body = "لديك موعد بعد 30 دقيقة مع {$barber->name}";

        $data = [
            'type' => 'appointment_reminder',
            'appointment_id' => (string) $appointment->id,
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            'screen' => 'appointment_details',
        ];

        $this->sendPushNotification($user, $title, $body, $data);
    }

    /**
     *  إرسال إشعار لجميع الزبائن عند إضافة خدمة جديدة
     */
    public function notifyAllCustomersAboutNewService(BarberService $service, User $barber): void
    {
        $customers = User::role('customer')
            ->where('is_active', true)
            ->whereNotNull('fcm_token')
            ->get();

        if ($customers->isEmpty()) {
            return;
        }

        $title = ' خدمة جديدة متاحة';
        $body = "{$barber->name} أضاف خدمة جديدة: {$service->name} بسعر {$service->price} ";

        $data = [
            'type' => 'new_service',
            'service_id' => (string) $service->id,
            'barber_id' => (string) $barber->id,
            'barber_name' => $barber->name,
            'service_name' => $service->name,
            'service_price' => (string) $service->price,
            'duration_minutes' => (string) $service->duration_minutes,
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            'screen' => 'services_list',
        ];

        foreach ($customers as $customer) {
            $this->sendPushNotification($customer, $title, $body, $data);
            usleep(50000);
        }
    }

    /**
     * الحصول على أسماء الخدمات كنص
     */
    private function getServicesNames(Appointment $appointment): string
    {
        if ($appointment->services_details) {
            $services = json_decode($appointment->services_details, true);
            if (is_array($services) && !empty($services)) {
                $names = array_column($services, 'name');
                return implode(' + ', $names);
            }
        }

        if ($appointment->services) {
            $serviceIds = json_decode($appointment->services, true);
            if (is_array($serviceIds) && !empty($serviceIds)) {
                $services = BarberService::whereIn('id', $serviceIds)->get();
                return $services->pluck('name')->implode(' + ');
            }
        }

        if ($appointment->service) {
            return $appointment->service->name;
        }

        return 'خدمات الحلاقة';
    }

    /**
     * تنسيق الوقت
     */
    private function formatTime($time): string
    {
        if (!$time) return '';
        if ($time instanceof \Carbon\Carbon) {
            return $time->format('g:i A');
        }
        return \Carbon\Carbon::parse($time)->format('g:i A');
    }

    /**
     * تنسيق التاريخ
     */
    private function formatDate($date): string
    {
        if (!$date) return '';
        if ($date instanceof \Carbon\Carbon) {
            return $date->format('Y-m-d');
        }
        return \Carbon\Carbon::parse($date)->format('Y-m-d');
    }


    /**
     *  إرسال إشعار لزبائن صالون محدد عند إضافة خدمة جديدة
     */
    public function notifySalonCustomersAboutNewService(BarberService $service, User $barber, Salon $salon): void
    {
        // جلب الزبائن الذين حجزوا في هذا الصالون سابقاً
        $customers = User::role('customer')
            ->where('is_active', true)
            ->whereNotNull('fcm_token')
            ->whereHas('appointments', function ($query) use ($salon) {
                $query->where('salon_id', $salon->id);
            })
            ->distinct()
            ->get();

        if ($customers->isEmpty()) {
            // إذا لم يوجد زبائن سابقين، أرسل للجميع
            $this->notifyAllCustomersAboutNewService($service, $barber);
            return;
        }

        $title = ' خدمة جديدة في ' . $salon->name;
        $body = "تمت إضافة خدمة جديدة: {$service->name} بسعر {$service->price}  بواسطة {$barber->name}";

        $data = [
            'type' => 'new_service',
            'service_id' => (string) $service->id,
            'barber_id' => (string) $barber->id,
            'salon_id' => (string) $salon->id,
            'salon_name' => $salon->name,
            'barber_name' => $barber->name,
            'service_name' => $service->name,
            'service_price' => (string) $service->price,
            'duration_minutes' => (string) $service->duration_minutes,
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            'screen' => 'services_list',
        ];

        $sentCount = 0;

        foreach ($customers as $customer) {
            if ($this->sendPushNotification($customer, $title, $body, $data)) {
                $sentCount++;
            }
            usleep(50000);
        }

        Log::info('New service notification sent to salon customers', [
            'service_id' => $service->id,
            'salon_id' => $salon->id,
            'customers_count' => $customers->count(),
            'sent_count' => $sentCount,
        ]);
    }

    /**
     *  إرسال إشعار لمدير الصالون عند إضافة خدمة جديدة
     */
    public function notifySalonOwnerAboutNewService(BarberService $service, User $barber, Salon $salon): void
    {
        $salonOwner = $salon->owner;

        if (!$salonOwner) {
            Log::warning('Salon owner not found', ['salon_id' => $salon->id]);
            return;
        }

        // إذا لم يكن لمدير الصالون FCM Token
        if (!$salonOwner->fcm_token) {
            Log::info('Salon owner has no FCM token', ['owner_id' => $salonOwner->id]);
            return;
        }

        $title = ' خدمة جديدة مضافة';
        $body = "أضاف {$barber->name} خدمة جديدة: {$service->name} بسعر {$service->price}";

        $data = [
            'type' => 'new_service_for_owner',
            'service_id' => (string) $service->id,
            'barber_id' => (string) $barber->id,
            'barber_name' => $barber->name,
            'salon_id' => (string) $salon->id,
            'salon_name' => $salon->name,
            'service_name' => $service->name,
            'service_price' => (string) $service->price,
            'duration_minutes' => (string) $service->duration_minutes,
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            'screen' => 'services_management',
        ];

        $this->sendPushNotification($salonOwner, $title, $body, $data);

        Log::info('Salon owner notified about new service', [
            'service_id' => $service->id,
            'salon_id' => $salon->id,
            'owner_id' => $salonOwner->id,
        ]);
    }

    /**
     *  إرسال إشعار لجميع الزبائن ومدير الصالون عند إضافة خدمة جديدة (دالة شاملة)
     */
    public function notifyAllAboutNewService(BarberService $service, User $barber, Salon $salon): void
    {
        // 1. إرسال إشعار لجميع الزبائن
        $this->notifySalonCustomersAboutNewService($service, $barber, $salon);

        // 2. إرسال إشعار لمدير الصالون
        $this->notifySalonOwnerAboutNewService($service, $barber, $salon);

        Log::info('All notifications sent for new service', [
            'service_id' => $service->id,
            'barber_id' => $barber->id,
            'salon_id' => $salon->id,
        ]);
    }
    /**
 * إرسال إشعار للحلاق عند تعديل موعد
 */
public function notifyAppointmentUpdatedToBarber(Appointment $appointment): void
{
    $barber = $appointment->barber;
    $customer = $appointment->customer;

    if (!$barber) {
        return;
    }

    $newTime = $this->formatTime($appointment->appointment_time);
    $newDate = $this->formatDate($appointment->appointment_date);

    $title = ' تم تعديل موعد';
    $body = "تم تعديل موعد {$customer->name} إلى {$newTime} بتاريخ {$newDate}";

    $data = [
        'type' => 'appointment_updated',
        'appointment_id' => (string) $appointment->id,
        'customer_name' => $customer->name,
        'new_time' => $newTime,
        'new_date' => $newDate,
        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
        'screen' => 'appointment_details',
    ];

    $this->sendPushNotification($barber, $title, $body, $data);
}
}
