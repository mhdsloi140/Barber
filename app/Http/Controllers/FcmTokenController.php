<?php
// app/Http/Controllers/FcmTokenController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FcmTokenController extends Controller
{
    public function update(Request $request)
    {
        $request->validate(['fcm_token' => 'required|string']);

        $user = auth()->user();
        $user->fcm_token = $request->fcm_token;
        $user->save();

        return response()->json(['success' => true]);
    }
}
