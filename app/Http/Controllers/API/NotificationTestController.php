<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Notification\FirebaseNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NotificationTestController extends Controller
{
    protected $notificationService;

    public function __construct(FirebaseNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * إرسال إشعار باستخدام التوكن مباشرة
     * POST /api/notification/send-by-token
     */
    public function sendByToken(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string',
            'title' => 'required|string|max:100',
            'body' => 'required|string|max:500',
            'type' => 'nullable|string',
            'screen' => 'nullable|string',
        ]);

        try {
            // إنشاء مستخدم مؤقت للإرسال
            $tempUser = new User();
            $tempUser->id = 999999;
            $tempUser->name = 'Test User';
            $tempUser->fcm_token = $request->fcm_token;
            $tempUser->notifications_enabled = true;

            $data = [
                'type' => $request->type ?? 'direct_test',
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                'screen' => $request->screen ?? 'home',
                'sent_at' => now()->toDateTimeString(),
            ];

            $result = $this->notificationService->sendPushNotification(
                $tempUser,
                $request->title,
                $request->body,
                $data
            );

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => ' تم إرسال الإشعار بنجاح',
                    'data' => [
                        'token' => substr($request->fcm_token, 0, 30) . '...',
                        'title' => $request->title,
                        'body' => $request->body,
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => ' فشل إرسال الإشعار',
                    'error' => 'يرجى التحقق من صحة التوكن أو اتصال الجهاز'
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Send notification by token error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إرسال الإشعار',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * إرسال إشعار لمستخدم محدد (باستخدام user_id)
     * POST /api/notification/send-to-user
     */
    public function sendToUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'title' => 'required|string|max:100',
            'body' => 'required|string|max:500',
            'type' => 'nullable|string',
            'screen' => 'nullable|string',
        ]);

        try {
            $user = User::find($request->user_id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم غير موجود'
                ], 404);
            }

            if (empty($user->fcm_token)) {
                return response()->json([
                    'success' => false,
                    'message' => 'هذا المستخدم ليس لديه FCM Token مسجل',
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                ], 400);
            }

            if (!$user->notifications_enabled) {
                return response()->json([
                    'success' => false,
                    'message' => 'الإشعارات معطلة لهذا المستخدم',
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                ], 400);
            }

            $data = [
                'type' => $request->type ?? 'direct_to_user',
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                'screen' => $request->screen ?? 'home',
                'user_id' => (string) $user->id,
                'user_name' => $user->name,
                'sent_at' => now()->toDateTimeString(),
            ];

            $result = $this->notificationService->sendPushNotification(
                $user,
                $request->title,
                $request->body,
                $data
            );

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => ' تم إرسال الإشعار بنجاح',
                    'data' => [
                        'user_id' => $user->id,
                        'user_name' => $user->name,
                        'title' => $request->title,
                        'body' => $request->body,
                        'has_token' => !empty($user->fcm_token),
                        'token_preview' => substr($user->fcm_token, 0, 30) . '...',
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => ' فشل إرسال الإشعار',
                    'error' => 'يرجى المحاولة مرة أخرى'
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Send notification to user error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إرسال الإشعار',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * إرسال إشعار لجميع المستخدمين (بث)
     * POST /api/notification/broadcast
     */
    public function broadcast(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:100',
            'body' => 'required|string|max:500',
            'role' => 'nullable|in:admin,barber,customer,all',
            'type' => 'nullable|string',
        ]);

        try {
            $role = $request->role ?? 'all';

            // بناء الاستعلام حسب الدور
            $query = User::whereNotNull('fcm_token')
                ->where('notifications_enabled', true);

            if ($role !== 'all') {
                $query->role($role);
            }

            $users = $query->get();

            if ($users->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا توجد أجهزة متاحة لإرسال الإشعار',
                    'users_found' => 0
                ], 404);
            }

            $data = [
                'type' => $request->type ?? 'broadcast',
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                'screen' => 'home',
                'broadcast_at' => now()->toDateTimeString(),
            ];

            $sentCount = 0;
            $failedCount = 0;

            foreach ($users as $user) {
                $result = $this->notificationService->sendPushNotification(
                    $user,
                    $request->title,
                    $request->body,
                    $data
                );

                if ($result) {
                    $sentCount++;
                } else {
                    $failedCount++;
                }

                // تأخير بسيط لتجنب تجاوز الحدود
                usleep(50000);
            }

            return response()->json([
                'success' => true,
                'message' => "تم إرسال الإشعار إلى {$sentCount} جهاز",
                'data' => [
                    'total_users' => $users->count(),
                    'sent_count' => $sentCount,
                    'failed_count' => $failedCount,
                    'role' => $role,
                    'title' => $request->title,
                    'body' => $request->body,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Broadcast notification error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إرسال الإشعارات',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * التحقق من صحة التوكن
     * POST /api/notification/validate-token
     */
    public function validateToken(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string',
        ]);

        try {
            // إنشاء مستخدم مؤقت للتحقق
            $tempUser = new User();
            $tempUser->id = 999999;
            $tempUser->name = 'Test User';
            $tempUser->fcm_token = $request->fcm_token;
            $tempUser->notifications_enabled = true;

            // إرسال إشعار تجريبي قصير
            $data = [
                'type' => 'token_validation',
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                'screen' => 'home',
            ];

            $result = $this->notificationService->sendPushNotification(
                $tempUser,
                ' اختبار التوكن',
                'إذا وصلتك هذه الرسالة، فالتوكن صالح',
                $data
            );

            return response()->json([
                'success' => $result,
                'message' => $result ? ' التوكن صالح' : ' التوكن غير صالح أو منتهي الصلاحية',
                'token_preview' => substr($request->fcm_token, 0, 35) . '...',
                'token_length' => strlen($request->fcm_token),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء التحقق من التوكن',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
