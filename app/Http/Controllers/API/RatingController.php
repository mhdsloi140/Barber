<?php
// app/Http/Controllers/API/RatingController.php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\RatingRequest;
use App\Services\Rating\RatingService;
use Illuminate\Http\Request;

class RatingController extends Controller
{
    public function __construct(
        private RatingService $ratingService
    ) {}

    /**
     * إضافة تقييم جديد
     * POST /api/ratings
     */
    public function store(RatingRequest $request)
    {
        $result = $this->ratingService->addRating($request->validated());

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }

    /**
     * عرض تقييمات الحلاق
     * GET /api/ratings/barber/{barberId}
     */
    public function barberRatings($barberId)
    {
        $result = $this->ratingService->getBarberRatings((int) $barberId);

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }

    /**
     * عرض تقييمات الصالون
     * GET /api/ratings/salon/{salonId}
     */
    public function salonRatings($salonId)
    {
        $result = $this->ratingService->getSalonRatings((int) $salonId);

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }

    /**
     * عرض تقييماتي (التي كتبتها)
     * GET /api/ratings/my-ratings
     */
    public function myRatings()
    {
        $result = $this->ratingService->getMyRatings();

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }
}
