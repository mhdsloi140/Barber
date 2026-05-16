<?php
// app/Http/Controllers/API/Customer/FavoriteSalonController.php

namespace App\Http\Controllers\API\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\FavoriteSalonRequest;
use App\Services\Customer\FavoriteSalonService;

class FavoriteSalonController extends Controller
{
    public function __construct(
        private FavoriteSalonService $favoriteSalonService
    ) {}

 
    public function index()
    {
        $result = $this->favoriteSalonService->getFavorites(auth()->user());

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }

    /**
     * إضافة صالون إلى المفضلة
     * POST /api/customer/favorite-salons
     */
    public function store(FavoriteSalonRequest $request)
    {
        $result = $this->favoriteSalonService->addFavorite(
            auth()->user(),
            $request->salon_id
        );

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }


    public function destroy($salonId)
    {
        $result = $this->favoriteSalonService->removeFavorite(
            auth()->user(),
            $salonId
        );

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }


    public function check($salonId)
    {
        $result = $this->favoriteSalonService->checkFavorite(
            auth()->user(),
            $salonId
        );

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }


    public function stats()
    {
        $result = $this->favoriteSalonService->getFavoritesWithStats(auth()->user());

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }
}
