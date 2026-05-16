<?php
// app/Http/Controllers/API/Customer/FavoriteBarberController.php

namespace App\Http\Controllers\API\Customer;

use App\Http\Controllers\Controller;

use App\Http\Requests\Customer\FavoriteBarberRequest;
use App\Services\Customer\FavoriteBarberService;
use Illuminate\Http\Request;

class FavoriteBarberController extends Controller
{
    public function __construct(
        private FavoriteBarberService $favoriteService
    ) {}

    /**
     * جلب قائمة الحلاقين المفضلين
     * GET /api/customer/favorites
     */
    public function index()
    {
        $result = $this->favoriteService->getFavorites(auth()->user());

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }

    /**
     * إضافة حلاق إلى المفضلة
     * POST /api/customer/favorites
     */
    public function store(FavoriteBarberRequest $request)
    {
        $result = $this->favoriteService->addFavorite(
            auth()->user(),
            $request->barber_id
        );

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }

    /**
     * إزالة حلاق من المفضلة
     * DELETE /api/customer/favorites/{barberId}
     */
    public function destroy($barberId)
    {
        $result = $this->favoriteService->removeFavorite(
            auth()->user(),
            $barberId
        );

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }

    /**
     * التحقق مما إذا كان الحلاق مفضلاً
     * GET /api/customer/favorites/check/{barberId}
     */
    public function check($barberId)
    {
        $result = $this->favoriteService->checkFavorite(
            auth()->user(),
            $barberId
        );

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }
}
