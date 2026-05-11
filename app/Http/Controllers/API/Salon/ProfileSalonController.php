<?php
// app/Http/Controllers/API/Salon/ProfileSalonController.php

namespace App\Http\Controllers\API\Salon;

use App\Http\Controllers\Controller;
use App\Http\Requests\Salon\UpdateSalonRequest;
use App\Services\Salon\UpdateSalonService;
use Illuminate\Http\Request;

class ProfileSalonController extends Controller
{
    public function __construct(
        private UpdateSalonService $updateSalonService
    ) {}

    /**
     * عرض بيانات الصالون الشخصية مع التقييمات
     * GET /api/salon/profile
     */
    public function show()
    {
        $result = $this->updateSalonService->showSalonProfile();

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }

   
    public function update(UpdateSalonRequest $request)
    {

        $data = $request->validated();


        if ($request->hasFile('avatar')) {
            $data['avatar'] = $request->file('avatar');
        }

        if ($request->hasFile('new_images')) {
            $data['new_images'] = $request->file('new_images');
        }


        if ($request->has('delete_image_ids')) {
            $data['delete_image_ids'] = $request->input('delete_image_ids');
        }


        if ($request->has('working_hours')) {
            $data['working_hours'] = $request->input('working_hours');
        }


        if ($request->has('password')) {
            $data['password'] = $request->input('password');
        }

        $result = $this->updateSalonService->updateSalon($data);

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ], $result->statusCode);
    }
}
