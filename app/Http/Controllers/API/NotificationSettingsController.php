<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationSettingsController extends Controller
{
    /**
     * جلب حالة الإشعارات الحالية للمستخدم
     */
    public function getStatus(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'notifications_enabled' => (bool) $user->notifications_enabled,
                'has_fcm_token' => !empty($user->fcm_token),
                'can_receive' => $user->canReceiveNotifications(),
            ],
        ]);
    }

    /**
     * تفعيل الإشعارات
     */
    public function enable(Request $request)
    {
        $user = $request->user();
        $user->enableNotifications();

        return response()->json([
            'success' => true,
            'message' => 'تم تفعيل الإشعارات بنجاح',
            'data' => [
                'notifications_enabled' => true,
            ],
        ]);
    }

    /**
     * تعطيل الإشعارات
     */
    public function disable(Request $request)
    {
        $user = $request->user();
        $user->disableNotifications();

        return response()->json([
            'success' => true,
            'message' => 'تم تعطيل الإشعارات بنجاح',
            'data' => [
                'notifications_enabled' => false,
            ],
        ]);
    }

    /**
     * تبديل حالة الإشعارات (تشغيل/إيقاف)
     */
    public function toggle(Request $request)
    {
        $user = $request->user();
        $newStatus = $user->toggleNotifications();

        $message = $newStatus ? 'تم تفعيل الإشعارات' : 'تم تعطيل الإشعارات';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'notifications_enabled' => $newStatus,
            ],
        ]);
    }

    /**
     * تحديث حالة الإشعارات (مع إمكانية إرسال القيمة)
     */
    public function update(Request $request)
    {
        $request->validate([
            'enabled' => 'required|boolean',
        ]);

        $user = $request->user();

        if ($request->enabled) {
            $user->enableNotifications();
        } else {
            $user->disableNotifications();
        }

        return response()->json([
            'success' => true,
            'message' => $request->enabled ? 'تم تفعيل الإشعارات' : 'تم تعطيل الإشعارات',
            'data' => [
                'notifications_enabled' => $request->enabled,
            ],
        ]);
    }
}
