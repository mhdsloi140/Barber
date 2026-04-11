<?php
// app/Http/Controllers/API/Salon/DashboardController.php

namespace App\Http\Controllers\API\Salon;

use App\Http\Controllers\Controller;
use App\Services\Salon\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboardService
    ) {}

 
    public function getBarbersCount()
    {
        $result = $this->dashboardService->getBarbersCount(auth()->user());

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }
}
