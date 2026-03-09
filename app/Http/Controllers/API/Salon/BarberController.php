<?php

namespace App\Http\Controllers\API\Salon;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBarberRequest;
use App\Services\BarberService;
use Illuminate\Http\Request;


class BarberController extends Controller
{
    public function __construct(
        private BarberService $barberService
    ) {}

    /**
     * عرض كل الحلاقين التابعين لصاحب الصالون
     */
    public function index()
    {
        $result = $this->barberService->getBarbers(auth()->user());

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'] ?? null,
            'data' => $result['data'] ?? null,
        ], $result['status']);
    }

    /**
     * إضافة حلاق جديد
     */
    public function store(StoreBarberRequest $request)
    {
        $result = $this->barberService->addBarber(
            $request->validated(),
            auth()->user()
        );

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
        ], $result['status']);
    }

    /**
     * عرض بيانات حلاق معين
     */
    public function show($id)
    {
        $result = $this->barberService->getBarber(auth()->user(), $id);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'] ?? null,
            'data' => $result['data'] ?? null,
        ], $result['status']);
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
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
        ], $result['status']);
    }

    /**
     * حذف حلاق
     */
    public function destroy($id)
    {
        $result = $this->barberService->deleteBarber(auth()->user(), $id);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
        ], $result['status']);
    }
}
