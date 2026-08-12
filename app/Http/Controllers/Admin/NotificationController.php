<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\Notification\FirebaseNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    protected $firebaseService;

    public function __construct(FirebaseNotificationService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * جلب جميع الإشعارات للمستخدم الحالي
     * GET /admin/notifications
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();

            $notifications = $user->notifications()
                ->orderBy('created_at', 'desc')
                ->get();

            $formatted = $notifications->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'title' => $notification->title ?? 'إشعار جديد',
                    'body' => $notification->body ?? 'لديك إشعار جديد',
                    'type' => $notification->type ?? 'info',
                    'icon' => $this->getIconByType($notification->type),
                    'is_read' => (bool) $notification->is_read,
                    'created_at' => $notification->created_at->diffForHumans(),
                    'created_at_raw' => $notification->created_at,
                    'data' => $notification->data ?? [],
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formatted,
                'count' => $formatted->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching notifications: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الإشعارات',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * جلب عدد الإشعارات غير المقروءة
     * GET /admin/notifications/unread
     */
    public function unread(Request $request)
    {
        try {
            $user = Auth::user();
            $count = $user->unreadNotifications()->count();

            return response()->json([
                'success' => true,
                'count' => $count,
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching unread count: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب عدد الإشعارات غير المقروءة',
                'count' => 0,
            ], 500);
        }
    }

    /**
     * تحديد إشعار كمقروء
     * PUT /admin/notifications/mark-read/{id}
     */
    public function markAsRead($id)
    {
        try {
            $user = Auth::user();
            $notification = $user->notifications()->find($id);

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'الإشعار غير موجود',
                ], 404);
            }

            $notification->update(['is_read' => true]);

            Log::info('Notification marked as read', [
                'user_id' => $user->id,
                'notification_id' => $id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديد الإشعار كمقروء',
            ]);

        } catch (\Exception $e) {
            Log::error('Error marking notification as read: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تحديد الإشعار كمقروء',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * تحديد جميع الإشعارات كمقروءة
     * PUT /admin/notifications/mark-all-read
     */
    public function markAllAsRead()
    {
        try {
            $user = Auth::user();
            $count = $user->unreadNotifications()->count();

            $user->unreadNotifications()->update(['is_read' => true]);

            Log::info('All notifications marked as read', [
                'user_id' => $user->id,
                'count' => $count,
            ]);

            return response()->json([
                'success' => true,
                'message' => "تم تحديد {$count} إشعار كمقروء",
                'count' => $count,
            ]);

        } catch (\Exception $e) {
            Log::error('Error marking all notifications as read: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تحديد الإشعارات كمقروءة',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * حذف إشعار
     * DELETE /admin/notifications/{id}
     */
    public function destroy($id)
    {
        try {
            $user = Auth::user();
            $notification = $user->notifications()->find($id);

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'الإشعار غير موجود',
                ], 404);
            }

            $notification->delete();

            Log::info('Notification deleted', [
                'user_id' => $user->id,
                'notification_id' => $id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم حذف الإشعار بنجاح',
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting notification: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء حذف الإشعار',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * حذف جميع الإشعارات المقروءة
     * DELETE /admin/notifications/read
     */
    public function destroyRead()
    {
        try {
            $user = Auth::user();
            $count = $user->notifications()->where('is_read', true)->count();

            $user->notifications()->where('is_read', true)->delete();

            Log::info('All read notifications deleted', [
                'user_id' => $user->id,
                'count' => $count,
            ]);

            return response()->json([
                'success' => true,
                'message' => "تم حذف {$count} إشعار مقروء",
                'count' => $count,
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting read notifications: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء حذف الإشعارات المقروءة',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * الحصول على أيقونة حسب نوع الإشعار
     */
    private function getIconByType(?string $type): string
    {
        $icons = [
            'new_salon_pending' => 'fa-store',
            'new_salon' => 'fa-store',
            'new_salon_owner_web' => 'fa-store',
            'new_appointment' => 'fa-calendar-check',
            'new_appointment_owner' => 'fa-calendar-check',
            'appointment_approved' => 'fa-check-circle',
            'appointment_rejected' => 'fa-times-circle',
            'appointment_cancelled_by_customer' => 'fa-ban',
            'appointment_cancelled_owner' => 'fa-ban',
            'appointment_completed' => 'fa-check-circle',
            'appointment_updated' => 'fa-edit',
            'appointment_reminder' => 'fa-clock',
            'new_service' => 'fa-cut',
            'new_service_for_owner' => 'fa-cut',
            'offer' => 'fa-tag',
            'salon_activated' => 'fa-check-circle',
            'salon_rejected' => 'fa-times-circle',
            'info' => 'fa-info-circle',
            'warning' => 'fa-exclamation-triangle',
            'success' => 'fa-check-circle',
            'error' => 'fa-exclamation-circle',
        ];

        return $icons[$type] ?? 'fa-bell';
    }
}
