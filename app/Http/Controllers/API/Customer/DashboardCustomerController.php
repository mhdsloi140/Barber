<?php


namespace App\Http\Controllers\API\Customer;

use App\Http\Controllers\Controller;
use App\Services\Customer\CustomerDashboardService;

class DashboardCustomerController extends Controller
{
    public function __construct(
        private CustomerDashboardService $dashboardService
    ) {}

    /**
     * الصفحة الرئيسية للزبون
     * GET /api/customer/dashboard
     */
    public function index()
    {
        $result = $this->dashboardService->getDashboard(auth()->user());

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }
}
