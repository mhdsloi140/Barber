<?php
// app/Http/Controllers/API/Notification/FcmTokenController.php

namespace App\Http\Controllers\API\Notification;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class FcmTokenController extends Controller
{
    /**
     * تحديث توكن Firebase للمستخدم
     */
    public function update(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string',
        ]);

        $user = auth()->user();
        $user->fcm_token = $request->fcm_token;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث توكن Firebase بنجاح',
        ]);
    }

    /**
     * حذف توكن Firebase (عند تسجيل الخروج)
     */
    public function destroy()
    {
        $user = auth()->user();
        $user->fcm_token = null;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف توكن Firebase بنجاح',
        ]);
    }
}
