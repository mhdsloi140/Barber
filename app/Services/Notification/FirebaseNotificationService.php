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

        try {
            $this->storeNotification($user, $title, $body, $data, $imageUrl);
            Log::info('Notification stored in database', [
                'user_id' => $user->id,
                'title' => $title,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to store notification', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        $token = $user->fcm_token;
        if (empty($token)) {
            Log::info('No FCM token found, notification stored only in DB', [
                'user_id' => $user->id,
            ]);
            return true;
        }

        if (isset($user->notifications_enabled) && !$user->notifications_enabled) {
            Log::info('User has notifications disabled, notification stored only in DB', [
                'user_id' => $user->id,
            ]);
            return true;
        }

        if (!$this->messaging) {
            Log::warning('Firebase messaging not available', ['user_id' => $user->id]);
            return true;
        }

        try {
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

            Log::info('Push notification sent successfully', [
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

            return true; 
        }
    }

    /**
     * تخزين الإشعار في قاعدة البيانات
     */
    private function storeNotification(User $user, string $title, string $body, array $data = [], ?string $imageUrl = null): void
    {
        try {
     
            \Illuminate\Support\Facades\DB::table('notifications')->insert([
                'type' => $data['type'] ?? 'info',
                'notifiable_type' => 'App\Models\User',
                'notifiable_id' => $user->id,
                'data' => json_encode([
                    'title' => $title,
                    'message' => $body,
                    'type' => $data['type'] ?? 'info',
                    'image_url' => $imageUrl,
                    ...$data
                ]),
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::debug('Notification stored successfully', [
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
        try {
            $barber = $appointment->barber;
            $customer = $appointment->customer;

            if (!$barber) {
                Log::error('Barber not found for appointment cancellation', ['appointment_id' => $appointment->id]);
                return;
            }

            $appointmentTime = $this->formatTime($appointment->appointment_time);
            $appointmentDate = $this->formatDate($appointment->appointment_date);
            $services = $this->getServicesNames($appointment);

            $title = 'تم إلغاء حجز';
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
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                'screen' => 'appointment_details',
            ];
            
            $this->sendPushNotification($barber, $title, $body, $data);

            Log::info('Cancellation notification sent to barber', [
                'appointment_id' => $appointment->id,
                'barber_id' => $barber->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to notify barber about cancellation: ' . $e->getMessage(), [
                'appointment_id' => $appointment->id ?? null,
                'barber_id' => $appointment->barber_id ?? null,
            ]);
        }
    }

    /**
     * إرسال إشعار لمدير الصالون عند إلغاء الحجز
     */
    public function notifySalonOwnerAboutCancelledAppointment(Appointment $appointment, ?string $reason = null): void
    {
        try {
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

            $title = 'تم إلغاء حجز في صالونك';
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

        } catch (\Exception $e) {
            Log::error('Failed to notify salon owner about cancelled appointment: ' . $e->getMessage(), [
                'appointment_id' => $appointment->id ?? null,
                'salon_id' => $salon->id ?? null,
            ]);
        }
    }

    /**
     * إرسال إشعار للحلاق عند إنشاء حجز جديد
     */
    public function notifyNewAppointmentToBarber(Salon $salon, Appointment $appointment): void
    {
        try {
            $barber = $appointment->barber;
            $customer = $appointment->customer;

            if (!$barber) {
                Log::error('Barber not found', ['appointment_id' => $appointment->id]);
                return;
            }

            $appointmentTime = $this->formatTime($appointment->appointment_time);
            $services = $this->getServicesNames($appointment);

            $title = 'لديك حجز';
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

            Log::info('New appointment notification sent to barber', [
                'barber_id' => $barber->id,
                'appointment_id' => $appointment->id,
                'salon_id' => $salon->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to notify barber about new appointment: ' . $e->getMessage(), [
                'barber_id' => $appointment->barber_id ?? null,
                'appointment_id' => $appointment->id ?? null,
            ]);
        }
    }

    /**
     * إرسال إشعار لمدير الصالون عند إنشاء حجز جديد
     */
    public function notifySalonOwnerAboutNewAppointment(Salon $salon, Appointment $appointment): void
    {
        try {
            $salonOwner = $salon->owner;

            if (!$salonOwner) {
                Log::warning('Salon owner not found', ['salon_id' => $salon->id]);
                return;
            }

            $customer = $appointment->customer;
            $barber = $appointment->barber;
            $appointmentTime = $this->formatTime($appointment->appointment_time);
            $services = $this->getServicesNames($appointment);

            $title = 'حجز جديد في صالونك';
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

            Log::info('New appointment notification sent to salon owner', [
                'salon_id' => $salon->id,
                'appointment_id' => $appointment->id,
                'salon_owner_id' => $salonOwner->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to notify salon owner about new appointment: ' . $e->getMessage(), [
                'salon_id' => $salon->id ?? null,
                'appointment_id' => $appointment->id ?? null,
            ]);
        }
    }

    /**
     * إرسال إشعار للزبون عند قبول الحجز
     */
    public function notifyAppointmentApprovedToCustomer(Appointment $appointment): void
    {
        try {
            $customer = $appointment->customer;

            if (!$customer) {
                Log::error('Customer not found for appointment', ['appointment_id' => $appointment->id]);
                return;
            }

            $barber = $appointment->barber;
            $appointmentTime = $this->formatTime($appointment->appointment_time);
            $services = $this->getServicesNames($appointment);

            $title = 'تم قبول حجزك';
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

            Log::info('Appointment approved notification sent to customer', [
                'appointment_id' => $appointment->id,
                'customer_id' => $customer->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to notify customer about appointment approval: ' . $e->getMessage(), [
                'appointment_id' => $appointment->id ?? null,
                'customer_id' => $appointment->customer_id ?? null,
            ]);
        }
    }

    /**
     * إرسال إشعار للزبون عند رفض الحجز
     */
    public function notifyAppointmentRejectedToCustomer(Appointment $appointment, ?string $reason = null): void
    {
        try {
            $customer = $appointment->customer;

            if (!$customer) {
                Log::error('Customer not found', ['appointment_id' => $appointment->id]);
                return;
            }

            $barber = $appointment->barber;
            $appointmentTime = $this->formatTime($appointment->appointment_time);
            $appointmentDate = $this->formatDate($appointment->appointment_date);
            $services = $this->getServicesNames($appointment);

            $title = 'تم إلغاء حجزك';
            $body = "تم إلغاء حجزك مع {$barber->name} في {$appointmentTime}";

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

            $this->sendPushNotification($customer, $title, $body, $data);

            Log::info('Appointment rejected notification sent to customer', [
                'appointment_id' => $appointment->id,
                'customer_id' => $customer->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to notify customer about appointment rejection: ' . $e->getMessage(), [
                'appointment_id' => $appointment->id ?? null,
                'customer_id' => $appointment->customer_id ?? null,
            ]);
        }
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

        $title = 'اكتمل حجزك';
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

        $title = 'تذكير بموعدك';
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

        $title = 'تم تعديل الحجز';
        $body = "   تم تعديل حجز  {$customer->name}   بتاريخ {$newDate}";

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

        $title = $action === 'subscribe' ? 'اشتراك في التنبيهات' : 'إلغاء الاشتراك';
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
     * إرسال إشعار لجميع الزبائن عند إضافة خدمة جديدة
     */
    public function notifyAllCustomersAboutNewService(BarberService $service, User $barber): void
    {
        try {
            $price = number_format($service->price, 0);

            $title = 'خدمة جديدة متاحة';
            $body = "{$barber->name} أضاف خدمة جديدة: {$service->name} بسعر {$price}";

            $data = [
                'type' => 'new_service',
                'service_id' => (string) $service->id,
                'barber_id' => (string) $barber->id,
                'barber_name' => $barber->name,
                'service_name' => $service->name,
                'service_price' => $price,
                'duration_minutes' => (string) $service->duration_minutes,
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                'screen' => 'services_list',
            ];

            $this->sendToTopic($this->getAllCustomersTopic(), $title, $body, $data);

            $customers = User::where('role', 'customer')->where('is_active', true)->get();
            
            foreach ($customers as $customer) {
                $this->sendPushNotification($customer, $title, $body, $data);
            }

            Log::info('New service notification sent to all customers', [
                'service_id' => $service->id,
                'customers_count' => $customers->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to notify all customers about new service: ' . $e->getMessage(), [
                'service_id' => $service->id ?? null,
            ]);
        }
    }

    /**
     * إرسال إشعار لزبائن صالون محدد عند إضافة خدمة جديدة
     */
    public function notifySalonCustomersAboutNewService(BarberService $service, User $barber, Salon $salon): void
    {
        $title = 'خدمة جديدة في ' . $salon->name;
        $body = "تمت إضافة خدمة جديدة: {$service->name} بسعر {$service->price} بواسطة {$barber->name}";

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
    private function formatPrice($price): string
    {
        $price = (float) $price;
        if (floor($price) == $price) {
            return (string) intval($price);
        }
        return number_format($price, 2);
    }

    public function notifySalonOwnerAboutNewService(BarberService $service, User $barber, Salon $salon): void
    {
        try {
            $salonOwner = $salon->owner;

            if (!$salonOwner) {
                Log::warning('Salon owner not found', ['salon_id' => $salon->id]);
                return;
            }

            $formattedPrice = $this->formatPrice($service->price);

            $title = 'خدمة جديدة مضافة';
            $body = "أضاف {$barber->name} خدمة جديدة: {$service->name} بسعر {$formattedPrice}";

            $data = [
                'type' => 'new_service_for_owner',
                'service_id' => (string) $service->id,
                'barber_id' => (string) $barber->id,
                'barber_name' => $barber->name,
                'salon_id' => (string) $salon->id,
                'salon_name' => $salon->name,
                'service_name' => $service->name,
                'service_price' => $formattedPrice,
                'duration_minutes' => (string) ($service->duration_minutes ?? 30),
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                'screen' => 'services_management',
            ];

            $this->sendPushNotification($salonOwner, $title, $body, $data);

            Log::info('New service notification sent to salon owner', [
                'service_id' => $service->id,
                'salon_id' => $salon->id,
                'salon_owner_id' => $salonOwner->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to notify salon owner about new service: ' . $e->getMessage(), [
                'service_id' => $service->id ?? null,
                'salon_id' => $salon->id ?? null,
            ]);
        }
    }

    // ===================== إشعارات الصالون الجديد للمديرين =====================

    public function notifyAdminsAboutNewSalon(Salon $salon, User $owner): void
    {
        $admins = User::role('admin')->get();

        if ($admins->isEmpty()) {
            Log::info('No admins found to notify about new salon');
            return;
        }

        $title = ' صالون جديد ينتظر الموافقة';
        $body = "الصالون: {$salon->name}\nالمالك: {$owner->name}\nرقم الهاتف: {$owner->phone}";

        $data = [
            'type' => 'new_salon_pending',
            'salon_id' => (string) $salon->id,
            'salon_name' => $salon->name,
            'salon_phone' => $salon->phone ?? '',
            'salon_address' => $salon->address ?? '',
            'owner_id' => (string) $owner->id,
            'owner_name' => $owner->name,
            'owner_phone' => $owner->phone,
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            'screen' => 'admin_pending_salons',
            'url' => route('admin.centers.show', $salon->id),
        ];

        $sentCount = 0;

        foreach ($admins as $admin) {
            $admin->notify(new \App\Notifications\NewSalonAdminNotification($salon, $owner));

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
     * إرسال إشعار لجميع المديرين (Web) عند إنشاء حساب صالون جديد
     */
    public function notifyAdminsAboutNewSalonOwnerWeb(User $salonOwner, Salon $salon): void
    {
        $admins = User::role('admin')->get();

        if ($admins->isEmpty()) {
            Log::info('No admins found to notify about new salon owner');
            return;
        }

        $title = ' حساب صالون جديد ينتظر الموافقة';
        $body = "تم إنشاء حساب جديد:\n"
              . "الصالون: {$salon->name}\n"
              . "المالك: {$salonOwner->name}\n"
              . "رقم الهاتف: {$salonOwner->phone}";

        $data = [
            'type' => 'new_salon_owner_web',
            'salon_id' => (string) $salon->id,
            'salon_name' => $salon->name,
            'salon_phone' => $salon->phone ?? '',
            'salon_address' => $salon->address ?? '',
            'owner_id' => (string) $salonOwner->id,
            'owner_name' => $salonOwner->name,
            'owner_phone' => $salonOwner->phone,
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            'screen' => 'admin_pending_salons',
            'url' => route('admin.centers.show', $salon->id),
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
     * إرسال إشعار عند تفعيل حساب صالون بواسطة المدير
     */
    public function notifySalonOwnerAboutActivation(User $salonOwner, Salon $salon): void
    {
        if (!$salonOwner->fcm_token) {
            Log::info('Salon owner has no FCM token', ['owner_id' => $salonOwner->id]);
            return;
        }

        $title = ' تم تفعيل صالونك بنجاح';
        $body = "تم تفعيل صالون {$salon->name} بنجاح.\nيمكنك الآن تسجيل الدخول والبدء في إدارة صالونك.";

        $data = [
            'type' => 'salon_activated',
            'salon_id' => (string) $salon->id,
            'salon_name' => $salon->name,
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            'screen' => 'salon_dashboard',
            'url' => route('admin.dashboard'),
        ];

        $this->sendPushNotification($salonOwner, $title, $body, $data);

        Log::info('Activation notification sent to salon owner', [
            'salon_id' => $salon->id,
            'owner_id' => $salonOwner->id,
        ]);
    }

    /**
     * إرسال إشعار عند رفض حساب صالون بواسطة المدير
     */
    public function notifySalonOwnerAboutRejection(User $salonOwner, string $reason = null): void
    {
        if (!$salonOwner->fcm_token) {
            Log::info('Salon owner has no FCM token', ['owner_id' => $salonOwner->id]);
            return;
        }

        $title = ' تم رفض طلب تسجيل صالونك';
        $body = "نأسف لإبلاغك أنه تم رفض طلب تسجيل صالونك.\n";

        if ($reason) {
            $body .= "السبب: {$reason}\n";
        }
        $body .= "يمكنك التواصل مع الدعم الفني للمزيد من المعلومات.";

        $data = [
            'type' => 'salon_rejected',
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            'screen' => 'contact_support',
        ];

        $this->sendPushNotification($salonOwner, $title, $body, $data);

        Log::info('Rejection notification sent to salon owner', [
            'owner_id' => $salonOwner->id,
            'reason' => $reason,
        ]);
    }

    // ===================== دوال مساعدة =====================

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
    public function formatTime($time): string
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
    public function formatDate($date): string
    {
        if (!$date) return '';
        if ($date instanceof \Carbon\Carbon) {
            return $date->format('Y-m-d');
        }
        return \Carbon\Carbon::parse($date)->format('Y-m-d');
    }

    /**
     * إرسال إشعار عند إلغاء حجز بواسطة مدير الصالون
     */
    public function notifyAppointmentCancelledByOwner(Appointment $appointment, ?string $reason = null): void
    {
        try {
            $barber = $appointment->barber;
            $customer = $appointment->customer;

            if ($barber) {
                $appointmentTime = $this->formatTime($appointment->appointment_time);
                
                $title = 'تم إلغاء حجز بواسطة المدير';
                $body = "تم إلغاء حجز {$customer->name} في {$appointmentTime} بواسطة مدير الصالون";
                if ($reason) {
                    $body .= "\nالسبب: {$reason}";
                }
                
                $data = [
                    'type' => 'appointment_cancelled_by_owner',
                    'appointment_id' => (string) $appointment->id,
                    'customer_name' => $customer->name ?? '',
                    'customer_phone' => $customer->phone ?? '',
                    'appointment_time' => $appointmentTime,
                    'appointment_date' => $this->formatDate($appointment->appointment_date),
                    'cancelled_by' => 'salon_owner',
                    'reason' => $reason ?? '',
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'screen' => 'appointment_details',
                ];
                
                $this->sendPushNotification($barber, $title, $body, $data);
                
                Log::info('Cancellation notification for barber', [
                    'appointment_id' => $appointment->id,
                    'barber_id' => $barber->id,
                ]);
            }

            if ($customer) {
                $appointmentTime = $this->formatTime($appointment->appointment_time);
                $barber = $appointment->barber;
                
                $title = 'تم إلغاء حجزك';
                $body = "تم إلغاء حجزك مع {$barber->name} في {$appointmentTime} بواسطة إدارة الصالون";
                if ($reason) {
                    $body .= "\nالسبب: {$reason}";
                }
                
                $data = [
                    'type' => 'appointment_cancelled_by_owner',
                    'appointment_id' => (string) $appointment->id,
                    'barber_name' => $barber->name ?? '',
                    'appointment_time' => $appointmentTime,
                    'appointment_date' => $this->formatDate($appointment->appointment_date),
                    'cancelled_by' => 'salon_owner',
                    'reason' => $reason ?? '',
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'screen' => 'appointment_details',
                ];
                
                $this->sendPushNotification($customer, $title, $body, $data);
                
                Log::info('Cancellation notification for customer', [
                    'appointment_id' => $appointment->id,
                    'customer_id' => $customer->id,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to send cancellation notifications: ' . $e->getMessage(), [
                'appointment_id' => $appointment->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}