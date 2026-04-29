<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function update(Request $request)
    {
        $user = Auth::user();

        $rules = [
            'name' => 'nullable|string|max:255',
            'phone' => [
                'nullable',
                'string',
                'min:10',
                'max:15',
                Rule::unique('users')->ignore($user->id),
            ],
        ];

        if ($request->filled('password')) {
            $rules['password'] = 'required|string|min:6|confirmed';
        }

        $request->validate($rules);

        if ($request->filled('name')) {
            $user->name = $request->name;
        }
        if ($request->filled('phone')) {
            $user->phone = $request->phone;
        }

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الملف الشخصي بنجاح'
        ]);
    }

    public function uploadImage(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048'
        ]);

        $user = Auth::user();
        $user->clearMediaCollection('avatar');
        $user->addMedia($request->file('avatar'))
            ->toMediaCollection('avatar');

        return response()->json([
            'success' => true,
            'image_url' => $user->getAvatarUrlAttribute(),
            'message' => 'تم رفع الصورة بنجاح'
        ]);
    }

    public function deleteImage()
    {
        $user = Auth::user();
        $user->clearMediaCollection('avatar');

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الصورة بنجاح'
        ]);
    }
}
