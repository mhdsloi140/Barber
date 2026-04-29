<?php
// app/Http/Controllers/API/Barber/WorkingHourController.php

namespace App\Http\Controllers\API\Barber;

use App\Http\Controllers\Controller;

use App\Http\Requests\Barber\BarberWorkingHoursRequest;
use App\Services\Barber\WorkingHourService;
use Illuminate\Http\Request;

class WorkingHourController extends Controller
{
    public function __construct(
        private WorkingHourService $workingHourService
    ) {
    }


    public function index()
    {
        $barber = auth()->user();
        $result = $this->workingHourService->getWorkingHours($barber);

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }


    public function update(BarberWorkingHoursRequest $request)
    {
        $barber = auth()->user();
        $result = $this->workingHourService->updateWorkingHours(
            $barber,
            $request->validated()
        );

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }
    public function getSalonWorkingDays()
    {
        $result = $this->workingHourService->getSalonWorkingDays(auth()->user());

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }

    public function reset()
    {
        $barber = auth()->user();
        $result = $this->workingHourService->resetWorkingHours($barber);

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }
}
