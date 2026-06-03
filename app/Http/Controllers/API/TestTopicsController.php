<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\Notification\FirebaseNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TestTopicsController extends Controller
{
    protected $notificationService;

    public function __construct(FirebaseNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * إرسال إشعار تجريبي لجميع الزبائن
     * POST /api/test/topics/all-customers
     */
    public function sendToAllCustomers(Request $request)
    {
        $request->validate([
            'title' => 'nullable|string|max:100',
            'body' => 'nullable|string|max:500',
        ]);

        $title = $request->title ?? ' إشعار تجريبي';
        $body = $request->body ?? 'هذه رسالة اختبار لجميع الزبائن من نظام الصالون';

        $result = $this->notificationService->sendToTopic(
            $this->notificationService->getAllCustomersTopic(),
            $title,
            $body,
            [
                'type' => 'test',
                'screen' => 'home',
                'test_mode' => true,
                'sent_at' => now()->toDateTimeString(),
            ]
        );

        return response()->json([
            'success' => $result,
            'message' => $result ? ' تم إرسال الإشعار لجميع الزبائن' : ' فشل إرسال الإشعار',
            'data' => [
                'topic' => $this->notificationService->getAllCustomersTopic(),
                'title' => $title,
                'body' => $body,
                'sent_at' => now()->toDateTimeString(),
            ]
        ]);
    }

    /**
     * إرسال إشعار تجريبي لجميع الحلاقين
     * POST /api/test/topics/all-barbers
     */
    public function sendToAllBarbers(Request $request)
    {
        $request->validate([
            'title' => 'nullable|string|max:100',
            'body' => 'nullable|string|max:500',
        ]);

        $title = $request->title ?? ' إشعار للحلاقين';
        $body = $request->body ?? 'هذه رسالة اختبار لجميع الحلاقين';

        $result = $this->notificationService->sendToTopic(
            $this->notificationService->getAllBarbersTopic(),
            $title,
            $body,
            [
                'type' => 'test',
                'screen' => 'dashboard',
                'test_mode' => true,
                'sent_at' => now()->toDateTimeString(),
            ]
        );

        return response()->json([
            'success' => $result,
            'message' => $result ? ' تم إرسال الإشعار لجميع الحلاقين' : ' فشل إرسال الإشعار',
            'data' => [
                'topic' => $this->notificationService->getAllBarbersTopic(),
                'title' => $title,
                'body' => $body,
                'sent_at' => now()->toDateTimeString(),
            ]
        ]);
    }

    /**
     * إرسال إشعار تجريبي لجميع المديرين
     * POST /api/test/topics/all-admins
     */
    public function sendToAllAdmins(Request $request)
    {
        $request->validate([
            'title' => 'nullable|string|max:100',
            'body' => 'nullable|string|max:500',
        ]);

        $title = $request->title ?? ' إشعار للمديرين';
        $body = $request->body ?? 'هذه رسالة اختبار لجميع المديرين';

        $result = $this->notificationService->sendToTopic(
            $this->notificationService->getAllAdminsTopic(),
            $title,
            $body,
            [
                'type' => 'test',
                'screen' => 'dashboard',
                'test_mode' => true,
                'sent_at' => now()->toDateTimeString(),
            ]
        );

        return response()->json([
            'success' => $result,
            'message' => $result ? ' تم إرسال الإشعار لجميع المديرين' : ' فشل إرسال الإشعار',
            'data' => [
                'topic' => $this->notificationService->getAllAdminsTopic(),
                'title' => $title,
                'body' => $body,
                'sent_at' => now()->toDateTimeString(),
            ]
        ]);
    }

    /**
     * إرسال إشعار تجريبي لزبائن صالون محدد
     * POST /api/test/topics/salon/{salonId}/customers
     */
    public function sendToSalonCustomers(Request $request, $salonId)
    {
        $request->validate([
            'title' => 'nullable|string|max:100',
            'body' => 'nullable|string|max:500',
        ]);

        $title = $request->title ?? " صالون رقم {$salonId}";
        $body = $request->body ?? "هذه رسالة اختبار لزبائن صالون رقم {$salonId}";

        $result = $this->notificationService->sendToTopic(
            $this->notificationService->getSalonCustomersTopic((int) $salonId),
            $title,
            $body,
            [
                'type' => 'test',
                'salon_id' => (string) $salonId,
                'screen' => 'salon_details',
                'test_mode' => true,
                'sent_at' => now()->toDateTimeString(),
            ]
        );

        return response()->json([
            'success' => $result,
            'message' => $result ? " تم إرسال الإشعار لزبائن صالون رقم {$salonId}" : ' فشل إرسال الإشعار',
            'data' => [
                'topic' => $this->notificationService->getSalonCustomersTopic((int) $salonId),
                'salon_id' => $salonId,
                'title' => $title,
                'body' => $body,
                'sent_at' => now()->toDateTimeString(),
            ]
        ]);
    }

    /**
     * إرسال إشعار تجريبي لحلاقين صالون محدد
     * POST /api/test/topics/salon/{salonId}/barbers
     */
    public function sendToSalonBarbers(Request $request, $salonId)
    {
        $request->validate([
            'title' => 'nullable|string|max:100',
            'body' => 'nullable|string|max:500',
        ]);

        $title = $request->title ?? " إشعار لحلاقي صالون رقم {$salonId}";
        $body = $request->body ?? "هذه رسالة اختبار لحلاقي صالون رقم {$salonId}";

        $result = $this->notificationService->sendToTopic(
            $this->notificationService->getSalonBarbersTopic((int) $salonId),
            $title,
            $body,
            [
                'type' => 'test',
                'salon_id' => (string) $salonId,
                'screen' => 'dashboard',
                'test_mode' => true,
                'sent_at' => now()->toDateTimeString(),
            ]
        );

        return response()->json([
            'success' => $result,
            'message' => $result ? " تم إرسال الإشعار لحلاقي صالون رقم {$salonId}" : ' فشل إرسال الإشعار',
            'data' => [
                'topic' => $this->notificationService->getSalonBarbersTopic((int) $salonId),
                'salon_id' => $salonId,
                'title' => $title,
                'body' => $body,
                'sent_at' => now()->toDateTimeString(),
            ]
        ]);
    }

    /**
     * إرسال عرض تجريبي لجميع الزبائن
     * POST /api/test/topics/offer
     */
    public function sendOffer(Request $request)
    {
        $request->validate([
            'title' => 'nullable|string|max:100',
            'body' => 'nullable|string|max:500',
            'coupon_code' => 'nullable|string|max:50',
        ]);

        $title = $request->title ?? ' عرض خاص';
        $body = $request->body ?? 'خصم 30% على جميع الخدمات اليوم فقط!';
        $couponCode = $request->coupon_code ?? 'WELCOME30';

        $result = $this->notificationService->sendOfferToAllCustomers(
            $title,
            $body,
            [
                'coupon_code' => $couponCode,
                'type' => 'offer',
                'test_mode' => true,
                'sent_at' => now()->toDateTimeString(),
            ]
        );

        return response()->json([
            'success' => $result,
            'message' => $result ? ' تم إرسال العرض لجميع الزبائن' : ' فشل إرسال العرض',
            'data' => [
                'topic' => $this->notificationService->getOffersTopic(),
                'title' => $title,
                'body' => $body,
                'coupon_code' => $couponCode,
                'sent_at' => now()->toDateTimeString(),
            ]
        ]);
    }

    /**
     * إرسال إشعار إلى Topic مخصص
     * POST /api/test/topics/custom
     */
    public function sendToCustomTopic(Request $request)
    {
        $request->validate([
            'topic' => 'required|string',
            'title' => 'required|string|max:100',
            'body' => 'required|string|max:500',
            'type' => 'nullable|string',
        ]);

        $result = $this->notificationService->sendToTopic(
            $request->topic,
            $request->title,
            $request->body,
            [
                'type' => $request->type ?? 'custom_test',
                'screen' => 'home',
                'test_mode' => true,
                'sent_at' => now()->toDateTimeString(),
            ]
        );

        return response()->json([
            'success' => $result,
            'message' => $result ? " تم إرسال الإشعار إلى topic: {$request->topic}" : ' فشل إرسال الإشعار',
            'data' => [
                'topic' => $request->topic,
                'title' => $request->title,
                'body' => $request->body,
                'sent_at' => now()->toDateTimeString(),
            ]
        ]);
    }

    /**
     * الحصول على قائمة بجميع Topics المتاحة
     * GET /api/test/topics/list
     */
    public function getTopicsList()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'topics' => [
                    [
                        'name' => 'all_customers',
                        'display_name' => 'جميع الزبائن',
                        'description' => 'إشعارات لجميع الزبائن المسجلين',
                        'example' => '/api/test/topics/all-customers'
                    ],
                    [
                        'name' => 'all_barbers',
                        'display_name' => 'جميع الحلاقين',
                        'description' => 'إشعارات لجميع الحلاقين',
                        'example' => '/api/test/topics/all-barbers'
                    ],
                    [
                        'name' => 'all_admins',
                        'display_name' => 'جميع المديرين',
                        'description' => 'إشعارات لجميع المديرين',
                        'example' => '/api/test/topics/all-admins'
                    ],
                    [
                        'name' => 'salon_{id}_customers',
                        'display_name' => 'زبائن صالون محدد',
                        'description' => 'إشعارات لزبائن صالون معين',
                        'example' => '/api/test/topics/salon/1/customers'
                    ],
                    [
                        'name' => 'salon_{id}_barbers',
                        'display_name' => 'حلاقي صالون محدد',
                        'description' => 'إشعارات لحلاقي صالون معين',
                        'example' => '/api/test/topics/salon/1/barbers'
                    ],
                    [
                        'name' => 'offers',
                        'display_name' => 'العروض',
                        'description' => 'عروض وخصومات',
                        'example' => '/api/test/topics/offer'
                    ],
                ]
            ]
        ]);
    }

    /**
     * إرسال إشعار لجميع الزبائن (بدون مصادقة - للتجربة فقط)
     * POST /api/test/topics/public/all-customers
     */
    public function publicSendToAllCustomers(Request $request)
    {
        // هذا الـ Route عام بدون مصادقة - للاختبار فقط
        $request->validate([
            'title' => 'nullable|string|max:100',
            'body' => 'nullable|string|max:500',
            'secret' => 'required|string',
        ]);

        // مفتاح سري للتأكد من أن الطلب آمن
        if ($request->secret !== 'test123') {
            return response()->json([
                'success' => false,
                'message' => 'المفتاح السري غير صحيح'
            ], 401);
        }

        $title = $request->title ?? 'إشعار تجريبي عام';
        $body = $request->body ?? 'هذه رسالة اختبار عامة';

        $result = $this->notificationService->sendToTopic(
            $this->notificationService->getAllCustomersTopic(),
            $title,
            $body,
            [
                'type' => 'public_test',
                'screen' => 'home',
                'sent_at' => now()->toDateTimeString(),
            ]
        );

        return response()->json([
            'success' => $result,
            'message' => $result ? 'تم إرسال الإشعار لجميع الزبائن' : ' فشل إرسال الإشعار',
            'sent_at' => now()->toDateTimeString(),
        ]);
    }
}
