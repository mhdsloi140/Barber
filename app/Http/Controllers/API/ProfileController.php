<?php
// app/Http/Controllers/API/ProfileController.php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\UpdateAvatarRequest;
use App\Services\ProfileService;
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

        return response()->json(
            $result->toArray(),
            $result->statusCode
        );
    }


    public function update(UpdateProfileRequest $request)
    {
        $result = $this->profileService->updateProfile(
            $request->validated(),
            $request->has('password')
        );

        return response()->json(
            $result->toArray(),
            $result->statusCode
        );
    }


    public function updateAvatar(UpdateAvatarRequest $request)
    {
        $result = $this->profileService->updateAvatar(
            $request->file('avatar')
        );

        return response()->json(
            $result->toArray(),
            $result->statusCode
        );
    }


    public function destroyAvatar()
    {
        $result = $this->profileService->deleteAvatar();

        return response()->json(
            $result->toArray(),
            $result->statusCode
        );
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $result = $this->profileService->changePassword(
            $request->current_password,
            $request->new_password
        );

        return response()->json(
            $result->toArray(),
            $result->statusCode
        );
    }


    public function updateNotifications(Request $request)
    {
        $request->validate([
            'notifications_enabled' => 'required|boolean',
        ]);

        $result = $this->profileService->updateNotifications(
            $request->notifications_enabled
        );

        return response()->json(
            $result->toArray(),
            $result->statusCode
        );
    }


    public function destroy()
    {
        $result = $this->profileService->deleteAccount();

        return response()->json(
            $result->toArray(),
            $result->statusCode
        );
    }
}
