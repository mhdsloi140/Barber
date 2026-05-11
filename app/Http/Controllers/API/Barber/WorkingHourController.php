<?php
// app/Http/Controllers/API/Barber/WorkingHourController.php

namespace App\Http\Controllers\API\Barber;

use App\Http\Controllers\Controller;
use App\Http\Requests\Barber\AddWorkingDaysRequest;
use App\Http\Requests\Barber\BarberWorkingHoursUpdateRequest;
use App\Http\Requests\Barber\AddMultipleWorkingDaysRequest;
use App\Services\Barber\WorkingHourService;
use Illuminate\Http\Request;

class WorkingHourController extends Controller
{
    public function __construct(
        private WorkingHourService $workingHourService
    ) {}

    /**
     * جلب أوقات عمل الحلاق الحالية
     */
    public function index()
    {
        $result = $this->workingHourService->getWorkingHours(auth()->user());

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }

    /**
     * حفظ أو تحديث أوقات العمل (يدعم إضافة أيام جديدة وتعديلها وحذفها)
     */
    public function update(BarberWorkingHoursUpdateRequest $request)
    {
        $result = $this->workingHourService->updateWorkingHours(
            auth()->user(),
            $request->validated()
        );

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }

    /**
     * إضافة عدة أيام جديدة دفعة واحدة
     */
    public function addMultipleDays(AddWorkingDaysRequest $request)
    {
        $result = $this->workingHourService->addMultipleWorkingDays(
            auth()->user(),
            $request->validated()
        );

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }

    /**
     * حذف يوم عمل
     */
    public function deleteDay($day)
    {
        $result = $this->workingHourService->deleteWorkingDay(auth()->user(), $day);

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }

    /**
     * جلب أيام عمل الصالون المفتوحة
     */
    public function getSalonOpenDays()
    {
        $result = $this->workingHourService->getSalonOpenDaysForBarber(auth()->user());

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }
}
