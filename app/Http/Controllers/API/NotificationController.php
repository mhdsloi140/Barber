<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    /**
     * جلب جميع إشعارات المستخدم (مع Pagination)
     * GET /api/notifications
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $notifications = Notification::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        // إحصائيات
        $stats = [
            'total' => Notification::where('user_id', $user->id)->count(),
            'unread' => Notification::where('user_id', $user->id)->where('is_read', false)->count(),
            'read' => Notification::where('user_id', $user->id)->where('is_read', true)->count(),
        ];

        return response()->json([
            'success' => true,
            'statistics' => $stats,
            'notifications' => $notifications,
        ]);
    }

    /**
     * جلب الإشعارات غير المقروءة فقط
     * GET /api/notifications/unread
     */
    public function unread(Request $request)
    {
        $user = $request->user();

        $notifications = Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'count' => $notifications->count(),
            'notifications' => $notifications,
        ]);
    }

    /**
     * عدد الإشعارات غير المقروءة (Badge)
     * GET /api/notifications/badge
     */
    public function badge(Request $request)
    {
        $user = $request->user();

        $count = Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'success' => true,
            'unread_count' => $count,
        ]);
    }

    /**
     * تحديد إشعار كمقروء
     * PUT /api/notifications/{id}/read
     */
    public function markAsRead(Request $request, $id)
    {
        $user = $request->user();

        $notification = Notification::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'الإشعار غير موجود',
            ], 404);
        }

        $notification->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديد الإشعار كمقروء',
        ]);
    }

    /**
     * تحديد جميع الإشعارات كمقروءة
     * PUT /api/notifications/read-all
     */
    public function markAllAsRead(Request $request)
    {
        $user = $request->user();

        $count = Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => "تم تحديد {$count} إشعار كمقروء",
            'marked_count' => $count,
        ]);
    }

    /**
     * حذف إشعار
     * DELETE /api/notifications/{id}
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        $notification = Notification::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'الإشعار غير موجود',
            ], 404);
        }

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الإشعار بنجاح',
        ]);
    }

    /**
     * حذف جميع الإشعارات
     * DELETE /api/notifications
     */
    public function destroyAll(Request $request)
    {
        $user = $request->user();

        $count = Notification::where('user_id', $user->id)->count();

        Notification::where('user_id', $user->id)->delete();

        return response()->json([
            'success' => true,
            'message' => "تم حذف {$count} إشعار",
            'deleted_count' => $count,
        ]);
    }
}
