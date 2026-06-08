<?php

namespace App\Services\ultraMessage;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UltraMsgService
{
    protected string $instanceId;
    protected string $apiToken;
    protected string $baseUrl;
    protected bool $enabled;

    public function __construct()
    {
        $this->instanceId = config('services.ultramsg.instance_id', env('ULTRAMSG_INSTANCE_ID', '')) ?: '';
        $this->apiToken = config('services.ultramsg.api_token', env('ULTRAMSG_API_TOKEN', '')) ?: '';
        $this->baseUrl = config('services.ultramsg.base_url', env('ULTRAMSG_BASE_URL', 'https://api.ultramsg.com'));
        $this->enabled = (bool) config('services.ultramsg.enabled', env('WHATSAPP_ENABLED', false));

        if (empty($this->instanceId) || empty($this->apiToken)) {
            Log::warning('UltraMsg credentials not configured properly');
        }
    }

    /**
     * التحقق من صحة الإعدادات
     */
    public function isConfigured(): bool
    {
        return $this->enabled && !empty($this->instanceId) && !empty($this->apiToken);
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
            Log::warning('UltraMsg is not configured');
            return [
                'success' => false,
                'error' => 'خدمة واتساب غير مهيأة',
                'code' => 'NOT_CONFIGURED'
            ];
        }

        // التحقق من صحة الرقم (اختياري)
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

            $response = Http::timeout(15)->asForm()->post(
                "{$this->baseUrl}/{$this->instanceId}/messages/chat",
                [
                    'token' => $this->apiToken,
                    'to' => $formattedPhone,
                    'body' => $message,
                    'priority' => $priority,
                ]
            );

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
            "*{$otpCode}*\n\n" .
            " هذا الرمز صالح لمدة {$expiresInMinutes} دقائق فقط.\n" .
            "لا تشارك هذا الرمز مع أي شخص.\n\n" .
            "إذا لم تطلب هذا الرمز، يمكنك تجاهل هذه الرسالة.";

        return $this->sendMessage($phone, $message, 1);
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
            $response = Http::timeout(10)->get("{$this->baseUrl}/{$this->instanceId}/instance/status", [
                'token' => $this->apiToken
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
            'instance_id' => $this->instanceId,
            'api_token_preview' => substr($this->apiToken, 0, 10) . '...',
            'has_token' => !empty($this->apiToken),
            'enabled' => $this->enabled,
            'is_configured' => $this->isConfigured(),
        ];
    }

    /**
     * الحصول على الـ URL المستخدم للإرسال
     */
    public function getSendUrl(): string
    {
        return "{$this->baseUrl}/{$this->instanceId}/messages/chat";
    }
}
