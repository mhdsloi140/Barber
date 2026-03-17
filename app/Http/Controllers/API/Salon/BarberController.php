<?php
// app/Http/Controllers/API/Salon/BarberController.php

namespace App\Http\Controllers\API\Salon;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBarberRequest;
use App\Http\Requests\WorkingHoursRequest;
use App\Services\BarberService;
use Illuminate\Http\Request;

class BarberController extends Controller
{
    public function __construct(
        private BarberService $barberService
    ) {}

    /**
     * عرض كل الحلاقين (لصاحب الصالون)
     */
    public function index()
    {
        $result = $this->barberService->getBarbers(auth()->user());

        return response()->json([
            'success' => $result->success,
            'message' => $result->message ?? null,
            'data' => $result->data ?? null,
        ], $result->statusCode);
    }

    /**
     * إضافة حلاق جديد (لصاحب الصالون)
     */
    public function store(StoreBarberRequest $request)
    {
        $result = $this->barberService->addBarber(
            $request->validated(),
            auth()->user()
        );

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data ?? null,
        ], $result->statusCode);
    }

    /**
     * عرض بيانات حلاق معين
     */
    public function show($id)
    {
        $result = $this->barberService->getBarber(auth()->user(), $id);

        return response()->json([
            'success' => $result->success,
            'message' => $result->message ?? null,
            'data' => $result->data ?? null,
        ], $result->statusCode);
    }

    /**
     * تحديث بيانات حلاق
     */
    public function update(Request $request, $id)
    {
        $result = $this->barberService->updateBarber(
            auth()->user(),
            $id,
            $request->all()
        );

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data ?? null,
        ], $result->statusCode);
    }

    /**
     * إيقاف حلاق (تعطيل)
     */
    public function deactivate($id)
    {
        $result = $this->barberService->deactivateBarber(auth()->user(), $id);

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data ?? null,
        ], $result->statusCode);
    }

    /**
     * تفعيل حلاق
     */
    public function activate($id)
    {
        $result = $this->barberService->activateBarber(auth()->user(), $id);

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data ?? null,
        ], $result->statusCode);
    }

    /**
     * تبديل حالة حلاق
     */
    public function toggleStatus($id)
    {
        $result = $this->barberService->toggleBarberStatus(auth()->user(), $id);

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data ?? null,
        ], $result->statusCode);
    }

    /**
     * حذف حلاق
     */
    public function destroy($id)
    {
        $result = $this->barberService->deleteBarber(auth()->user(), $id);

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
        ], $result->statusCode);
    }

    // ========== دوال أوقات العمل (خاصة بالحلاق) ==========

    /**
     * جلب أوقات العمل الخاصة بالحلاق الحالي
     */
    public function getMyWorkingHours()
    {
        $result = $this->barberService->getWorkingHours(auth()->user());

        return response()->json([
            'success' => $result->success,
            'message' => $result->message ?? null,
            'data' => $result->data ?? null,
        ], $result->statusCode);
    }

    /**
     * تحديث أوقات العمل الخاصة بالحلاق الحالي
     */
    // public function updateMyWorkingHours(WorkingHoursRequest $request)
    // {
    //     $result = $this->barberService->updateWorkingHours(
    //         auth()->user(),
    //         $request->validated()
    //     );

    //     return response()->json([
    //         'success' => $result->success,
    //         'message' => $result->message,
    //         'data' => $result->data ?? null,
    //     ], $result->statusCode);
    // }
}
