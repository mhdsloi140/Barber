<?php

namespace App\Services\ultraMessage;

use Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\User;
class UltraMsgService
{
    protected string $instanceId;
    protected string $token;
    protected string $baseUrl;
    protected bool $enabled;

    public function __construct()
    {

        $this->instanceId = config('services.ultramsg.instance_id', '');
        $this->token = config('services.ultramsg.token', '');
        $this->baseUrl = config('services.ultramsg.base_url', 'https://api.ultramsg.com');
        $this->enabled = config('services.ultramsg.enabled', false);

        if ($this->instanceId && str_starts_with($this->instanceId, 'instance')) {
            $this->instanceId = str_replace('instance', '', $this->instanceId);
        }


    }

 
    protected function getApiUrl(string $endpoint = 'messages/chat'): string
    {

        return "{$this->baseUrl}/instance{$this->instanceId}/{$endpoint}";
    }

    /**
     * التحقق من صحة الإعدادات
     */
    public function isConfigured(): bool
    {
        return $this->enabled && !empty($this->instanceId) && !empty($this->token);
    }

    /**
     * تنسيق رقم الهاتف للواتساب (العراق فقط)
     */
    public function formatPhoneNumber(string $phone): string
    {
        // إزالة المسافات والشرطات والأقواس
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // إذا كان الرقم يبدأ بـ 0 (أرقام عراقية: 077, 078, 079)
        if (str_starts_with($phone, '0')) {
            $phone = '+964' . substr($phone, 1);
        }
        // إذا كان الرقم بدون + وبدون 00
        elseif (!str_starts_with($phone, '+') && !str_starts_with($phone, '00')) {
            $phone = '+964' . $phone;
        }
        // إزالة 00 من البداية إذا وجدت
        elseif (str_starts_with($phone, '00')) {
            $phone = '+' . substr($phone, 2);
        }

        return $phone;
    }

    /**
     * التحقق من صحة رقم هاتف عراقي
     */
    public function isValidPhoneNumber(string $phone): bool
    {
        $formattedPhone = $this->formatPhoneNumber($phone);
        return (bool) preg_match('/^\+964[0-9]{10}$/', $formattedPhone);
    }

    /**
     * إرسال رسالة عادية
     */
    public function sendMessage(string $phone, string $message, int $priority = 1): array
    {
        if (!$this->isConfigured()) {
            Log::warning('UltraMsg is not configured', [
                'enabled' => $this->enabled,
                'has_instance_id' => !empty($this->instanceId),
                'has_token' => !empty($this->token)
            ]);
            return [
                'success' => false,
                'error' => 'خدمة واتساب غير مهيأة',
                'code' => 'NOT_CONFIGURED'
            ];
        }

        // التحقق من صحة الرقم
        if (!$this->isValidPhoneNumber($phone)) {
            Log::warning('Invalid phone number format', ['phone' => $phone]);
            return [
                'success' => false,
                'error' => 'رقم الهاتف غير صحيح',
                'code' => 'INVALID_PHONE'
            ];
        }

        try {
            $formattedPhone = $this->formatPhoneNumber($phone);
            $url = $this->getApiUrl('messages/chat');

            Log::info('Sending WhatsApp message', [
                'url' => $url,
                'to' => $formattedPhone,
                'priority' => $priority
            ]);

            $response = Http::timeout(15)->asForm()->post($url, [
                'token' => $this->token,
                'to' => $formattedPhone,
                'body' => $message,
                'priority' => $priority,
            ]);

            if ($response->successful()) {
                $responseData = $response->json();

                if (isset($responseData['error'])) {
                    Log::error('UltraMsg API error', ['error' => $responseData['error']]);
                    return [
                        'success' => false,
                        'error' => $responseData['error'],
                        'code' => 'API_ERROR'
                    ];
                }

                Log::info('WhatsApp message sent', [
                    'phone' => $formattedPhone,
                    'message_id' => $responseData['id'] ?? null
                ]);

                return [
                    'success' => true,
                    'message_id' => $responseData['id'] ?? null,
                    'data' => $responseData,
                    'code' => 'SENT'
                ];
            }

            Log::error('WhatsApp send failed', [
                'phone' => $formattedPhone,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return [
                'success' => false,
                'error' => 'فشل إرسال الرسالة',
                'status' => $response->status(),
                'code' => 'SEND_FAILED'
            ];

        } catch (\Exception $e) {
            Log::error('WhatsApp exception: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => 'EXCEPTION'
            ];
        }
    }

    /**
     * إرسال رمز التحقق OTP
     */
    public function sendOTP(string $phone, string $otpCode, int $expiresInMinutes = 10): array
    {
        $message = " *رمز التحقق الخاص بك*\n\n" .
            "مرحباً بك في تطبيقنا!\n\n" .
            "رمز التحقق الخاص بحسابك هو:\n\n" .
            "{$otpCode}\n\n" .
            " هذا الرمز صالح لمدة {$expiresInMinutes} دقائق فقط.\n" .
            " لا تشارك هذا الرمز مع أي شخص.\n\n" .
            "إذا لم تطلب هذا الرمز، يمكنك تجاهل هذه الرسالة.";

        return $this->sendMessage($phone, $message, 5); // أولوية أعلى لـ OTP
    }

    /**
     * الحصول على حالة الـ Instance
     */
    public function getInstanceStatus(): array
    {
        if (!$this->isConfigured()) {
            return ['status' => 'not_configured'];
        }

        try {
            $url = $this->getApiUrl('instance/status');
            $response = Http::timeout(10)->get($url, [
                'token' => $this->token
            ]);

            return $response->json();
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * الحصول على الإعدادات للتشخيص
     */
    public function getDiagnostics(): array
    {
        return [
            'base_url' => $this->baseUrl,
            'instance_id' => $this->instanceId ? substr($this->instanceId, 0, 6) . '...' : null,
            'has_token' => !empty($this->token),
            'enabled' => $this->enabled,
            'is_configured' => $this->isConfigured(),
            'api_url' => $this->getApiUrl(),
        ];
    }

    /**
     * الحصول على الـ URL المستخدم للإرسال
     */
    public function getSendUrl(): string
    {
        return $this->getApiUrl('messages/chat');
    }
        public function sendCredentials(User $user, $salon, string $password, string $type = 'barber'): array
    {
        // التحقق من صحة الإعدادات
        if (!$this->isConfigured()) {
            Log::warning('Cannot send credentials: UltraMsg not configured');
            return [
                'success' => false,
                'error' => 'خدمة واتساب غير مهيأة',
                'code' => 'NOT_CONFIGURED'
            ];
        }

        // تنسيق رقم الهاتف
        $phone = $this->formatPhoneNumber($user->phone);
        if (!$this->isValidPhoneNumber($phone)) {
            Log::error('Invalid phone number for sending credentials', [
                'user_id' => $user->id,
                'phone' => $user->phone
            ]);
            return [
                'success' => false,
                'error' => 'رقم الهاتف غير صحيح',
                'code' => 'INVALID_PHONE'
            ];
        }

        // بناء الرسالة حسب نوع المستخدم (بدون رموز تعبيرية)
        $message = $this->buildCredentialsMessage($user, $salon, $password, $type);

        // إرسال الرسالة
        $result = $this->sendMessage($phone, $message, 5);

        if ($result['success']) {
            Log::info('Credentials sent successfully', [
                'user_id' => $user->id,
                'type' => $type,
                'phone' => $phone
            ]);
        } else {
            Log::error('Failed to send credentials', [
                'user_id' => $user->id,
                'type' => $type,
                'phone' => $phone,
                'error' => $result['error'] ?? 'Unknown error'
            ]);

            // تخزين كلمة المرور لإعادة المحاولة لاحقاً
            $this->storePendingPassword($user->id, $password);
        }

        return $result;
    }

    /**
     * بناء رسالة بيانات الدخول حسب نوع المستخدم (بدون رموز تعبيرية)
     */
    protected function buildCredentialsMessage(User $user, $salon, string $password, string $type): string
    {
        $salonName = $salon->name ?? $salon['name'] ?? 'صالوننا';
        $appUrl = env('APP_URL', 'https://barber-app.com');

        // اسم الدور بالعربية
        $roleName = $this->getRoleName($type);

        $message = "مرحباً بك في فريق {$salonName}\n\n"
                 . "تم إضافتك كـ {$roleName} في المنصة.\n\n"
                 . "بيانات الدخول الخاصة بك:\n"
                 . "----------------------------------------\n"
                 . "الاسم: {$user->name}\n"
                 . "رقم الجوال: {$user->phone}\n"
                 . "كلمة المرور: {$password}\n"
                 . "----------------------------------------\n\n"
                 . "تنبيه: يرجى تغيير كلمة المرور بعد أول تسجيل دخول.\n\n"
                 . "رابط التطبيق: {$appUrl}/login\n\n"
                 . "شكراً لانضمامك إلينا.";

        // إضافة تعليمات خاصة للحلاقين
        if ($type === 'barber') {
            $message .= "\n\nملاحظة: يمكنك إدارة مواعيدك وعمولاتك من لوحة التحكم الخاصة بك.";
        }

        return $message;
    }

    /**
     * الحصول على اسم الدور بالعربية
     */
    protected function getRoleName(string $type): string
    {
        return match($type) {
            'barber' => 'حلاق',
            'customer' => 'عميل',
            'admin' => 'مدير',
            default => 'مستخدم'
        };
    }

    /**
     * تخزين كلمة المرور المعلقة لإعادة المحاولة
     */
    protected function storePendingPassword(int $userId, string $password): void
    {
        $key = "pending_password_{$userId}";
        Cache::put($key, $password, now()->addHours(24));

        Log::info('Password stored for retry', [
            'user_id' => $userId,
            'key' => $key
        ]);
    }

    /**
     * استرجاع كلمة المرور المعلقة
     */
    public function getPendingPassword(int $userId): ?string
    {
        $key = "pending_password_{$userId}";
        return Cache
        ::get($key);
    }

    /**
     * حذف كلمة المرور المعلقة
     */
    public function clearPendingPassword(int $userId): void
    {
        $key = "pending_password_{$userId}";
        Cache::forget($key);
    }

    /**
     * إعادة محاولة إرسال بيانات الدخول
     */
    public function resendCredentials(User $user, $salon, string $type = 'barber'): array
    {
        $password = $this->getPendingPassword($user->id);

        if (!$password) {
            return [
                'success' => false,
                'error' => 'لا توجد كلمة مرور معلقة لهذا المستخدم',
                'code' => 'NO_PENDING_PASSWORD'
            ];
        }

        $result = $this->sendCredentials($user, $salon, $password, $type);

        if ($result['success']) {
            $this->clearPendingPassword($user->id);
        }

        return $result;
    }

    /**
     * إرسال رسالة مخصصة مع قالب (لأغراض عامة)
     */
    public function sendCustomTemplate(string $phone, string $name, string $password, string $salonName, string $role): array
    {
        $message = "مرحباً بك في {$salonName}\n\n"
                 . "تم إضافتك كـ {$role} بنجاح.\n\n"
                 . "بيانات الدخول:\n"
                 . "الاسم: {$name}\n"
                 . "كلمة المرور: {$password}\n\n"
                 ;

        return $this->sendMessage($phone, $message, 5);
    }

}