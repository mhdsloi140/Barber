<?php
// app/Http/Controllers/API/Notification/DeviceTokenController.php

namespace App\Http\Controllers\API\Notification;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    /**
     * حفظ توكن الجهاز
     */
    public function store(Request $request)
    {
        $request->validate([
            'device_token' => 'required|string',
            'device_type' => 'required|in:ios,android,web',
        ]);

        // تعطيل التوكنات القديمة لهذا الجهاز (نفس التوكن)
        DeviceToken::where('user_id', auth()->id())
            ->where('device_token', $request->device_token)
            ->update(['is_active' => false]);

        // إنشاء توكن جديد
        $token = DeviceToken::create([
            'user_id' => auth()->id(),
            'device_token' => $request->device_token,
            'device_type' => $request->device_type,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم حفظ التوكن بنجاح',
            'data' => $token,
        ]);
    }

    /**
     * حذف توكن الجهاز
     */
    public function destroy(Request $request)
    {
        $request->validate([
            'device_token' => 'required|string',
        ]);

        DeviceToken::where('user_id', auth()->id())
            ->where('device_token', $request->device_token)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف التوكن بنجاح',
        ]);
    }
}
