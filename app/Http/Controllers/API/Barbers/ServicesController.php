<?php
// app/Http/Controllers/API/Barber/BarberServiceController.php

namespace App\Http\Controllers\API\Barbers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Barber\BarberServiceRequest;
use App\Http\Requests\Barber\BarberUpdateServiceRequest;

use App\Services\Barber\BarberServiceService;
use Illuminate\Http\Request;

class ServicesController extends Controller
{
    public function __construct(
        private BarberServiceService $serviceService
    ) {}

    /**
     * عرض جميع خدمات الحلاق
     */
    public function index()
    {
        $result = $this->serviceService->getServices(auth()->user());

        return response()->json([
            'success' => $result->success,
            'message' => $result->message ?? null,
            'data' => $result->data ?? null,
        ], $result->statusCode);
    }

    /**
     * إضافة خدمة جديدة
     */
    public function store(BarberServiceRequest $request)
    {
        $result = $this->serviceService->addService(
            auth()->user(),
            $request->validated()
        );

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data ?? null,
        ], $result->statusCode);
    }

    /**
     * عرض خدمة محددة
     */
    public function show($id)
    {
        $result = $this->serviceService->getService(auth()->user(), $id);

        return response()->json([
            'success' => $result->success,
            'message' => $result->message ?? null,
            'data' => $result->data ?? null,
        ], $result->statusCode);
    }

    /**
     * تحديث خدمة
     */
    public function update(BarberUpdateServiceRequest $request, $id)
    {
        // dd($request->all());
        $result = $this->serviceService->updateService(
            auth()->user(),
            $id,
            $request->validated()
        );

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data ?? null,
        ], $result->statusCode);
    }

    /**
     * حذف خدمة (Soft Delete)
     */
    public function destroy($id)
    {
        $result = $this->serviceService->deleteService(auth()->user(), $id);

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
        ], $result->statusCode);
    }

    /**
     * حذف خدمة نهائياً
     */
    public function forceDelete($id)
    {
        $result = $this->serviceService->forceDeleteService(auth()->user(), $id);

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
        ], $result->statusCode);
    }

    /**
     * تبديل حالة الخدمة (تفعيل/تعطيل)
     */
    public function toggleStatus($id)
    {
        $result = $this->serviceService->toggleServiceStatus(auth()->user(), $id);

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data ?? null,
        ], $result->statusCode);
    }

    /**
     * عرض الخدمات المحذوفة (سلة المهملات)
     */
    public function trashed()
    {
        $result = $this->serviceService->getTrashedServices(auth()->user());

        return response()->json([
            'success' => $result->success,
            'message' => $result->message ?? null,
            'data' => $result->data ?? null,
        ], $result->statusCode);
    }

    /**
     * استعادة خدمة محذوفة
     */
    public function restore($id)
    {
        $result = $this->serviceService->restoreService(auth()->user(), $id);

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data ?? null,
        ], $result->statusCode);
    }
}
