<?php
// app/Services/Notification/FirebaseNotificationService.php

namespace App\Services\Notification;

use App\Models\User;
use App\Models\Salon;
use App\Models\Appointment;
use App\Models\BarberService;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Illuminate\Support\Facades\Log;

class FirebaseNotificationService
{
    protected $messaging;
    protected $isEnabled;

    public function __construct()
    {
        $this->isEnabled = false;

        try {
            $credentialsPath = storage_path('firebase/firebase_credentials.json');

            if (!file_exists($credentialsPath)) {
                Log::warning('Firebase credentials not found');
                return;
            }

            $factory = (new Factory())->withServiceAccount($credentialsPath);
            $this->messaging = $factory->createMessaging();
            $this->isEnabled = true;

            Log::info('Firebase initialized successfully');

        } catch (\Exception $e) {
            Log::error('Firebase init error: ' . $e->getMessage());
        }
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled && $this->messaging !== null;
    }

    /**
     * إرسال إشعار لمستخدم واحد
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): bool
    {
        if (!$this->isEnabled() || !$user->fcm_token) {
            return false;
        }

        return $this->sendToToken($user->fcm_token, $title, $body, $data);
    }

    /**
     * إرسال إشعار إلى توكن محدد
     */
    public function sendToToken(string $token, string $title, string $body, array $data = []): bool
    {
        try {
            $notification = Notification::create($title, $body);

            $message = CloudMessage::withTarget('token', $token)
                ->withNotification($notification)
                ->withData($data);

            $this->messaging->send($message);

            Log::info('Notification sent', ['token' => substr($token, 0, 20) . '...']);
            return true;

        } catch (\Exception $e) {
            Log::error('Send notification error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * إرسال إشعار لصاحب الصالون (مدير الصالون)
     */
    public function sendToSalonOwner(Salon $salon, string $title, string $body, array $data = []): bool
    {
        $owner = $salon->owner;

        if (!$owner || !$owner->fcm_token) {
            Log::warning('Salon owner has no FCM token', ['salon_id' => $salon->id]);
            return false;
        }

        return $this->sendToUser($owner, $title, $body, $data);
    }

    /**
     * إشعار عند إضافة خدمة جديدة
     */
    public function notifyNewService(Salon $salon, BarberService $service): bool
    {
        $title = '🆕 خدمة جديدة';
        $body = "تم إضافة خدمة جديدة: {$service->name}\n💰 السعر: {$service->price} ₪\n⏱️ المدة: {$service->duration_minutes} دقيقة";

        $data = [
            'type' => 'new_service',
            'service_id' => $service->id,
            'service_name' => $service->name,
            'service_price' => $service->price,
            'service_duration' => $service->duration_minutes,
            'salon_id' => $salon->id,
            'salon_name' => $salon->name,
            'timestamp' => now()->toIso8601String(),
            'action' => 'view_service'
        ];

        return $this->sendToSalonOwner($salon, $title, $body, $data);
    }

    /**
     * إشعار عند إنشاء حجز جديد للصالون
     */
    public function notifyNewAppointment(Salon $salon, Appointment $appointment): bool
    {
        $customerName = $appointment->customer->name ?? 'عميل';
        $barberName = $appointment->barber->name ?? 'حلاق';

        $title = '📅 حجز جديد';
        $body = "{$customerName} حجز موعد مع {$barberName}\n📅 {$appointment->appointment_date} - 🕐 {$appointment->appointment_time}";

        $data = [
            'type' => 'new_appointment',
            'appointment_id' => $appointment->id,
            'customer_name' => $customerName,
            'customer_phone' => $appointment->customer->phone ?? '',
            'barber_name' => $barberName,
            'date' => $appointment->appointment_date,
            'time' => $appointment->appointment_time,
            'salon_id' => $salon->id,
            'salon_name' => $salon->name,
            'timestamp' => now()->toIso8601String(),
            'action' => 'view_appointment'
        ];

        return $this->sendToSalonOwner($salon, $title, $body, $data);
    }

    /**
     * إشعار عند تأكيد الحجز
     */
    public function notifyAppointmentConfirmed(Appointment $appointment): bool
    {
        $salon = $appointment->salon;
        $title = '✅ تم تأكيد حجزك';
        $body = "تم تأكيد حجزك في {$salon->name}\n📅 {$appointment->appointment_date} - 🕐 {$appointment->appointment_time}";

        $data = [
            'type' => 'appointment_confirmed',
            'appointment_id' => $appointment->id,
            'status' => 'confirmed',
            'action' => 'view_appointment'
        ];

        return $this->sendToUser($appointment->customer, $title, $body, $data);
    }

    /**
     * إشعار عند إلغاء الحجز
     */
    public function notifyAppointmentCancelled(Appointment $appointment, string $reason = ''): bool
    {
        $salon = $appointment->salon;
        $title = '❌ تم إلغاء حجزك';
        $body = "تم إلغاء حجزك في {$salon->name}";
        if ($reason) {
            $body .= "\nالسبب: {$reason}";
        }

        $data = [
            'type' => 'appointment_cancelled',
            'appointment_id' => $appointment->id,
            'reason' => $reason,
            'action' => 'view_appointment'
        ];

        return $this->sendToUser($appointment->customer, $title, $body, $data);
    }
}
