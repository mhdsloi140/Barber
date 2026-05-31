<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Services\UpdateProfileServices;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(
        private UpdateProfileServices $profileService
    ) {}

    public function index()
    {
        $result = $this->profileService->getProfile();
        return response()->json($result->toArray(), $result->statusCode);
    }


    public function update(UpdateProfileRequest $request)
    {
        $result = $this->profileService->updateProfile($request);
        return response()->json($result->toArray(), $result->statusCode);
    }

    /**
     * حذف الصورة الشخصية فقط ()
     */
    public function deleteAvatar()
    {
        $result = $this->profileService->deleteAvatar();
        return response()->json($result->toArray(), $result->statusCode);
    }
}
