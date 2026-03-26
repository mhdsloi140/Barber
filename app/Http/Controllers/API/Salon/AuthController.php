<?php
// app/Http/Controllers/API/AuthController.php

namespace App\Http\Controllers\API\Salon;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterSalonOwnerRequest;
use App\Services\Auth\LoginService;

use App\Services\Salon\RegisterService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private RegisterService $registerService,

    ) {}



       public function registerSalonOwner(RegisterSalonOwnerRequest $request)
    {
        // dd('as');
        $result = $this->registerService->registerSalonOwner(
            $request->validated(),
            $request->file('image')
        );

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }




}
