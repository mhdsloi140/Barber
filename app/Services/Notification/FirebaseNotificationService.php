<?php

namespace App\Services\Notification;

use App\Models\Appointment;
use App\Models\Salon;
use App\Models\User;
use App\Models\BarberService;
use App\Models\Notification as NotificationModel;
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
     * تحويل القيمة إلى نص
     */
    private function safeString($value, string $default = ''): string
    {
        if (is_array($value)) {
            return $default ?: 'إشعار';
        }
        return (string) $value;
    }

    /**
     * إرسال إشعار Push وتخزينه في قاعدة البيانات
     */
    public function sendPushNotification(User $user, $title, $body, array $data = [], ?string $imageUrl = null): bool
    {
        // تحويل title و body إلى string بشكل آمن
        $title = $this->safeString($title, 'إشعار');
        $body = $this->safeString($body, 'لديك إشعار جديد');

        // التأكد من أنهما ليسا فارغين
        if (empty($title)) {
            $title = 'إشعار';
        }
        if (empty($body)) {
            $body = 'لديك إشعار جديد';
        }

        // التحقق من وجود المستخدم
        if (!$user) {
            Log::error('User is null in sendPushNotification');
            return false;
        }

        // التحقق من وجود FCM Token
        $token = $user->fcm_token;
        if (empty($token)) {
            Log::info('No FCM token found for user', ['user_id' => $user->id]);
            return false;
        }

        // التحقق من تفعيل الإشعارات للمستخدم
        if (isset($user->notifications_enabled) && !$user->notifications_enabled) {
            Log::info('User has notifications disabled', ['user_id' => $user->id]);
            return false;
        }

        // تخزين الإشعار في قاعدة البيانات
        try {
            $this->storeNotification($user, $title, $body, $data, $imageUrl);
        } catch (\Exception $e) {
            Log::error('Failed to store notification', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }

        // التحقق من وجود Firebase Messaging
        if (!$this->messaging) {
            Log::warning('Firebase messaging not available', ['user_id' => $user->id]);
            return false;
        }

        try {
            // إنشاء الإشعار
            $notification = Notification::create($title, $body);

            $message = CloudMessage::withTarget('token', $token)
                ->withNotification($notification)
                ->withData($data)
                ->withWebPushConfig([
                    'headers' => [
                        'Urgency' => 'high'
                    ],
                    'notification' => [
                        'icon' => url('/img/logo2.png'),
                        'badge' => url('/img/logo2.png'),
                        'vibrate' => [200, 100, 200],
                        'requireInteraction' => true,
                        'actions' => [
                            ['action' => 'open', 'title' => 'فتح'],
                            ['action' => 'close', 'title' => 'إغلاق']
                        ]
                    ],
                    'fcm_options' => [
                        'link' => $data['url'] ?? url('/admin/dashboard')
                    ]
                ])
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
                'title' => $title,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send push notification', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            if (
                str_contains($e->getMessage(), 'NOT_FOUND') ||
                str_contains($e->getMessage(), 'UNREGISTERED') ||
                str_contains($e->getMessage(), 'Invalid argument')
            ) {

                $user->update(['fcm_token' => null]);
                Log::info('Invalid FCM token removed', ['user_id' => $user->id]);
            }

            return false;
        }
    }

    /**
     * تخزين الإشعار في قاعدة البيانات
     */
    private function storeNotification(User $user, string $title, string $body, array $data = [], ?string $imageUrl = null): void
    {
        try {
            NotificationModel::create([
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

    // ===================== دوال الإشعارات العادية =====================


    /**
     * إرسال إشعار للحلاق عند إلغاء الحجز
     */
    public function notifyAppointmentCancelledToBarber(Appointment $appointment, ?string $reason = null): void
    {
        $barber = $appointment->barber;
        $customer = $appointment->customer;

        if (!$barber) {
            Log::error('Barber not found for appointment cancellation', ['appointment_id' => $appointment->id]);
            return;
        }

        $appointmentTime = $this->formatTime($appointment->appointment_time);
        $appointmentDate = $this->formatDate($appointment->appointment_date);
        $services = $this->getServicesNames($appointment);

        $title = ' تم إلغاء حجز';
        $body = "تم إلغاء حجز {$customer->name} في {$appointmentTime}";



        $data = [
            'type' => 'appointment_cancelled_by_customer',
            'appointment_id' => (string) $appointment->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'appointment_time' => $appointmentTime,
            'appointment_date' => $appointmentDate,
            'services' => $services,
            'total_price' => (string) $appointment->total_price,
            // 'reason' => $reason ?? '',
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            'screen' => 'appointment_details',
        ];

        $this->sendPushNotification($barber, $title, $body, $data);

        Log::info('Cancellation notification sent to barber', [
            'appointment_id' => $appointment->id,
            'barber_id' => $barber->id,
        ]);
    }

    /**
     * إرسال إشعار لمدير الصالون عند إلغاء الحجز
     */
    public function notifySalonOwnerAboutCancelledAppointment(Appointment $appointment, ?string $reason = null): void
    {
        $salon = $appointment->salon;

        if (!$salon) {
            Log::error('Salon not found for appointment', ['appointment_id' => $appointment->id]);
            return;
        }

        $salonOwner = $salon->owner;
        $customer = $appointment->customer;
        $barber = $appointment->barber;

        if (!$salonOwner) {
            Log::warning('Salon owner not found', ['salon_id' => $appointment->salon_id]);
            return;
        }

        $appointmentTime = $this->formatTime($appointment->appointment_time);
        $appointmentDate = $this->formatDate($appointment->appointment_date);
        $services = $this->getServicesNames($appointment);

        $title = ' تم إلغاء حجز في صالونك';
        $body = "تم إلغاء حجز {$customer->name} مع {$barber->name} في {$appointmentTime}";



        $data = [
            'type' => 'appointment_cancelled_owner',
            'appointment_id' => (string) $appointment->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'barber_name' => $barber->name,
            'barber_id' => (string) $barber->id,
            'salon_name' => $salon->name,
            'appointment_time' => $appointmentTime,
            'appointment_date' => $appointmentDate,
            'services' => $services,
            'total_price' => (string) $appointment->total_price,
            'reason' => $reason ?? '',
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            'screen' => 'appointment_details',
        ];

        $this->sendPushNotification($salonOwner, $title, $body, $data);

        Log::info('Cancellation notification sent to salon owner', [
            'appointment_id' => $appointment->id,
            'salon_id' => $salon->id,
            'owner_id' => $salonOwner->id,
        ]);
    }
    /**
     * إرسال إشعار للحلاق عند إنشاء حجز جديد
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
        $body = "لديك حجز جدي من {$customer->name} في {$appointmentTime}";

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
     * إرسال إشعار لمدير الصالون عند إنشاء حجز جديد
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
     * إرسال إشعار للزبون عند قبول الحجز
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
     * إرسال إشعار لجميع المديرين (Web) عند إنشاء حساب صالون جديد
     */
    public function notifyAdminsAboutNewSalonOwnerWeb(User $salonOwner, Salon $salon): void
    {
        // جلب جميع المستخدمين الذين لديهم دور admin
        $admins = User::role('admin')
            ->whereNotNull('fcm_token')
            ->get();

        if ($admins->isEmpty()) {
            Log::info('No admins found with FCM token to notify about new salon owner');
            return;
        }

        $title = ' حساب صالون جديد';
        $body = "تم إنشاء حساب جديد: {$salon->name} بواسطة {$salonOwner->name}";

        $data = [
            'type' => 'new_salon_owner_web',
            'salon_id' => (string) $salon->id,
            'salon_name' => $salon->name,
            'salon_phone' => $salon->phone,
            'salon_address' => $salon->address,
            'owner_id' => (string) $salonOwner->id,
            'owner_name' => $salonOwner->name,
            'owner_phone' => $salonOwner->phone,
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            'screen' => 'admin_salon_details',
            'url' => route('admin.salons.show', $salon->id), // رابط للويب
        ];

        $sentCount = 0;

        foreach ($admins as $admin) {
            if ($this->sendPushNotification($admin, $title, $body, $data)) {
                $sentCount++;
            }
            usleep(50000);
        }

        Log::info('Web admin notifications sent for new salon owner', [
            'salon_id' => $salon->id,
            'salon_name' => $salon->name,
            'owner_id' => $salonOwner->id,
            'admins_count' => $admins->count(),
            'sent_count' => $sentCount,
        ]);
    }

    /**
     * إرسال إشعار للزبون عند رفض الحجز
     */
    public function notifyAppointmentRejectedToCustomer(Appointment $appointment, ?string $reason = null): void
    {
        $customer = $appointment->customer;

        if (!$customer) {
            Log::error('Customer not found', ['appointment_id' => $appointment->id]);
            return;
        }

        Log::info('Attempting to send rejection notification to customer', [
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'has_fcm_token' => !empty($customer->fcm_token),
            'notifications_enabled' => $customer->notifications_enabled,
        ]);

        //  التحقق من وجود FCM Token
        if (empty($customer->fcm_token)) {
            Log::warning('Customer has no FCM token', ['customer_id' => $customer->id]);
            return;
        }

        // ✅ التحقق من تفعيل الإشعارات
        if (!$customer->notifications_enabled) {
            Log::info('Customer notifications disabled', ['customer_id' => $customer->id]);
            return;
        }

        $barber = $appointment->barber;
        $appointmentTime = $this->formatTime($appointment->appointment_time);
        $appointmentDate = $this->formatDate($appointment->appointment_date);
        $services = $this->getServicesNames($appointment);

        $title = ' تم رفض حجزك';
        $body = "تم رفض حجزك مع {$barber->name} في {$appointmentTime}";

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
            'appointment_date' => $appointmentDate,
            'services' => $services,
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            'screen' => 'appointment_details',
        ];

        $result = $this->sendPushNotification($customer, $title, $body, $data);

        Log::info('Rejection notification result', [
            'customer_id' => $customer->id,
            'result' => $result,
        ]);
    }

    /**
     * إرسال إشعار للزبون عند إكمال الحجز
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
     * إرسال إشعار تذكير للزبون قبل الموعد
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
     * إرسال إشعار للحلاق عند تعديل موعد
     */
    public function notifyAppointmentUpdatedToBarber(Appointment $appointment): void
    {
        $barber = $appointment->barber;
        $customer = $appointment->customer;

        if (!$barber) {
            Log::error('Barber not found for appointment update', ['appointment_id' => $appointment->id]);
            return;
        }

        $newTime = $this->formatTime($appointment->appointment_time);
        $newDate = $this->formatDate($appointment->appointment_date);
        $oldTime = $appointment->getOriginal('appointment_time');
        $oldDate = $appointment->getOriginal('appointment_date');

        $title = ' تم تعديل موعد';
        $body = "تم تعديل موعد {$customer->name} إلى {$newTime} بتاريخ {$newDate}";

        $data = [
            'type' => 'appointment_updated',
            'appointment_id' => (string) $appointment->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'new_time' => $newTime,
            'new_date' => $newDate,
            'old_time' => $this->formatTime($oldTime),
            'old_date' => $this->formatDate($oldDate),
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            'screen' => 'appointment_details',
        ];

        $this->sendPushNotification($barber, $title, $body, $data);
    }

    /**
     * إرسال إشعار لجميع المديرين (Admin) عند إنشاء صالون جديد
     */
    public function notifyAdminsAboutNewSalon(Salon $salon, User $owner): void
    {
        $admins = User::role('admin')
            ->whereNotNull('fcm_token')
            ->get();

        if ($admins->isEmpty()) {
            Log::info('No admins found with FCM token to notify about new salon');
            return;
        }

        $title = ' صالون جديد';
        $body = "تم إنشاء صالون جديد: {$salon->name} بواسطة {$owner->name}";

        $data = [
            'type' => 'new_salon',
            'salon_id' => (string) $salon->id,
            'salon_name' => $salon->name,
            'salon_phone' => $salon->phone,
            'salon_address' => $salon->address,
            'owner_id' => (string) $owner->id,
            'owner_name' => $owner->name,
            'owner_phone' => $owner->phone,
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            'screen' => 'admin_salon_details',
        ];

        $sentCount = 0;

        foreach ($admins as $admin) {
            if ($this->sendPushNotification($admin, $title, $body, $data)) {
                $sentCount++;
            }
            usleep(50000);
        }

        Log::info('Admin notifications sent for new salon', [
            'salon_id' => $salon->id,
            'salon_name' => $salon->name,
            'admins_count' => $admins->count(),
            'sent_count' => $sentCount,
        ]);
    }

    /**
     * إرسال إشعار لجميع الزبائن عند إضافة خدمة جديدة
     */
    public function notifyAllCustomersAboutNewService(BarberService $service, User $barber): void
    {
        $title = ' خدمة جديدة متاحة';
        $body = "{$barber->name} أضاف خدمة جديدة: {$service->name} بسعر {$service->price}";

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

        $this->sendToTopic($this->getAllCustomersTopic(), $title, $body, $data);
    }

    /**
     * إرسال إشعار لزبائن صالون محدد عند إضافة خدمة جديدة
     */
    public function notifySalonCustomersAboutNewService(BarberService $service, User $barber, Salon $salon): void
    {
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

        $topic = $this->getSalonCustomersTopic($salon->id);
        $this->sendToTopic($topic, $title, $body, $data);

        Log::info('New service notification sent to salon topic', [
            'service_id' => $service->id,
            'salon_id' => $salon->id,
            'topic' => $topic,
        ]);
    }

    /**
     * إرسال إشعار لمدير الصالون عند إضافة خدمة جديدة
     */
    public function notifySalonOwnerAboutNewService(BarberService $service, User $barber, Salon $salon): void
    {
        $salonOwner = $salon->owner;

        if (!$salonOwner) {
            Log::warning('Salon owner not found', ['salon_id' => $salon->id]);
            return;
        }

        if (!$salonOwner->fcm_token) {
            Log::info('Salon owner has no FCM token', ['owner_id' => $salonOwner->id]);
            return;
        }

        $title = ' خدمة جديدة مضافة';
        $body = "أضاف {$barber->name} خدمة جديدة: {$service->name} بسعر {$service->price} ";

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
    }

    // ===================== دوال Topics =====================

    /**
     * الحصول على اسم topic الخاص بالصالون (للزبائن)
     */
    public function getSalonCustomersTopic(int $salonId): string
    {
        return "salon_{$salonId}_customers";
    }

    /**
     * الحصول على اسم topic الخاص بالصالون (للحلاقين)
     */
    public function getSalonBarbersTopic(int $salonId): string
    {
        return "salon_{$salonId}_barbers";
    }

    /**
     * الحصول على topic جميع الزبائن
     */
    public function getAllCustomersTopic(): string
    {
        return "all_customers";
    }

    /**
     * الحصول على topic جميع الحلاقين
     */
    public function getAllBarbersTopic(): string
    {
        return "all_barbers";
    }

    /**
     * الحصول على topic جميع المديرين
     */
    public function getAllAdminsTopic(): string
    {
        return "all_admins";
    }

    /**
     * الحصول على topic العروض
     */
    public function getOffersTopic(): string
    {
        return "offers";
    }

    /**
     * إرسال أمر للتطبيق بالاشتراك في Topic
     */
    public function sendSubscribeCommand(User $user, string $topic, string $action = 'subscribe'): bool
    {
        $data = [
            'type' => 'topic_command',
            'topic' => $topic,
            'action' => $action,
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
        ];

        $title = $action === 'subscribe' ? ' اشتراك في التنبيهات' : ' إلغاء الاشتراك';
        $body = $action === 'subscribe'
            ? "تم الاشتراك في تنبيهات {$topic}"
            : "تم إلغاء الاشتراك من {$topic}";

        return $this->sendPushNotification($user, $title, $body, $data);
    }

    /**
     * إرسال إشعار إلى موضوع (Topic)
     */
    public function sendToTopic(string $topic, string $title, string $body, array $data = []): bool
    {
        Log::info('🔔 [TOPIC] Attempting to send notification', [
            'topic' => $topic,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);

        if (!$this->messaging) {
            Log::warning('Firebase messaging not available');
            return false;
        }

        try {
            $notification = Notification::create($title, $body);

            $message = CloudMessage::withTarget('topic', $topic)
                ->withNotification($notification)
                ->withData($data)
                ->withWebPushConfig([
                    'headers' => [
                        'Urgency' => 'high'
                    ],
                    'notification' => [
                        'icon' => url('/img/logo2.png'),
                        'badge' => url('/img/logo2.png'),
                        'vibrate' => [200, 100, 200],
                        'requireInteraction' => true,
                    ],
                    'fcm_options' => [
                        'link' => $data['url'] ?? url('/')
                    ]
                ])
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

            Log::info('Notification sent to topic', [
                'topic' => $topic,
                'title' => $title,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send notification to topic', [
                'topic' => $topic,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * إرسال إشعار إلى مواضيع متعددة
     */
    public function sendToTopics(array $topics, string $title, string $body, array $data = []): void
    {
        foreach ($topics as $topic) {
            $this->sendToTopic($topic, $title, $body, $data);
            usleep(50000);
        }
    }

    /**
     * اشتراك زبون في Topics صالونه المفضل
     */
    public function subscribeCustomerToSalonTopics(User $customer, Salon $salon): void
    {
        $topics = [
            $this->getSalonCustomersTopic($salon->id),
            $this->getOffersTopic(),
        ];

        foreach ($topics as $topic) {
            $this->sendSubscribeCommand($customer, $topic, 'subscribe');
        }

        Log::info('Customer subscribed to salon topics', [
            'customer_id' => $customer->id,
            'salon_id' => $salon->id,
            'topics' => $topics,
        ]);
    }

    /**
     * إلغاء اشتراك زبون من Topics صالونه
     */
    public function unsubscribeCustomerFromSalonTopics(User $customer, Salon $salon): void
    {
        $topics = [
            $this->getSalonCustomersTopic($salon->id),
            $this->getOffersTopic(),
        ];

        foreach ($topics as $topic) {
            $this->sendSubscribeCommand($customer, $topic, 'unsubscribe');
        }

        Log::info('Customer unsubscribed from salon topics', [
            'customer_id' => $customer->id,
            'salon_id' => $salon->id,
        ]);
    }

    /**
     * إرسال عرض خاص لجميع الزبائن
     */
    public function sendOfferToAllCustomers(string $offerTitle, string $offerBody, array $extraData = []): bool
    {
        $data = array_merge([
            'type' => 'offer',
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            'screen' => 'offers',
        ], $extraData);

        return $this->sendToTopic($this->getOffersTopic(), $offerTitle, $offerBody, $data);
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
        if (!$time)
            return '';
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
        if (!$date)
            return '';
        if ($date instanceof \Carbon\Carbon) {
            return $date->format('Y-m-d');
        }
        return \Carbon\Carbon::parse($date)->format('Y-m-d');
    }
}
